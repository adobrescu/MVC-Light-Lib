<?php

namespace alib\model;
//1:50:17 PM (de vineri 02.oct.20150
/*
 * 
 * 1) $groups->users->currentRecord->contentItems->contentTranslations
 * 2) $groups->users->contentItems->contentTranslations
 * 3) $groups->users->currentRecord->contentItems->currentRecord->contentTranslations
 * 
 * In toate exemplele de mai sus, path-urile vor incarca aceleasi inregistrari din baza de date (si anume toate traducerile 
 * pentru toate contentItem-urile tuturor utilizatorilor din toate grupurile).
 * 
 * !Nota. In exemplele de mai sus, exemplele de path-uri sint obiecte diferite!!!
 * In primul exemplu, contentTranslations va contine datele intr-un array in care key-urile sint rpk-uri de users (de adancime 2),
 * in al doilea exemplu rpk-urile sint goale (adancime 0 pentru ca nu exista niciun currentrecord in path), iar in al treilea
 * cheile sint rpk-uri de contentItems (adancime 3).
 * 
 * DAR, la parcurgere prin moveFirst, moveNext ale acestor path-uri, vor fi parcurse inregistrari diferite;
 * 
 * // presupunand ca intai se face un $groups->moveFirst():
 * 
 * 1) intoarce toate traducerile pentru toate contentItem-urile pentru primul user al primului grup;
 * 2) intoarce toate contentItem-urile;
 * 3) intoarce traducerile penru primul contentItem al primului user al primului grup;
 * 
 * 1. Toate path-urile care incarca aceleasi date au un "shared data" (fiecare are o referinta la aceleasi date). 
 * Primul path apelat va incarca datele din shared data. 
 * 2. La "apel", un path va cauta primul parent/ancestor care este marcat "current" si va crea, incarcand din shared data, un array
 * cu cheile reprezentand rpk-ul parentului
 * 
 * 
 * Shared data ar putea sta intr-o variabila statica (cu posibilitatea de a o sterge pentru a elibera memoria) pentru a putea fi folosita
 * in toata aplicatia.
 * 
 * ! users si users->currentRecord puncteaza catre aceleasi date, doar ca al doilea instruieste path-urile care vin dupa el (si nu sint urmate de currentRecord)
 * sa-si tina datele in format
 * user_rpk => record_rpk
 * 
 * !! $groups->users->currentRecord->contentItems->moveNext() il va modifica/avansa pe:
 * 
 * $groups->users->currentRecord->contentItems->currentRecord
 * 
 * si va influenta continutul lui:
 * 
 * $groups->users->currentRecord->contentItems->currentRecord->contentTranslations
 * 
 * dar nu si pe al:
 * 
 * $groups->users->currentRecord->contentItems->contentTranslation
 * 
 * !!! $groups->users->moveNext() trebuie sa mute 2 cursoare cu ceva de genul "move2ParentRPK":
 * 
 * $groups->users->currentRecord->contentItems
 * $groups->users->currentRecord->contentItems->currentRecord->contentTranslations
 * 
 * Si nu ar mai fi nevoie ca contentItems si contentTranslations sa se uite la ancestori sa vada care e marcat "current".
 * 
 * De fapt, operatiunea de mutare a cursoarelor e facuta recursiv, $groups->users->moveNext() muta cursorul lui $groups->users->currentRecord->contentItems si
 * acesta pe al lui $groups->users->currentRecord->contentItems->currentRecord->contentTranslations
 * 
 * 
 */
class Recordset
{
	protected $database, $databaseName, $tablesPrefix;
	public $data, $loaded, $dataCursor;
	protected $eof;
	protected $tableName, $idColumns;
	protected $parentPath, $currentParentPath, $currentParentPathDepth=0;
	protected $clause, $clauseName, $fkIndex, $fkDirection;
	static $___relationships;
	protected $paths;
	protected $usedAsCurrent=false;
	protected $columns, $columnAliases;
	protected $_currentRecord;
	protected $parentRPK='';
	
	static protected $___sharedData, $___sharedDataLoaded;
	public $sharedData, $sharedDataLoaded;
	
	public function __construct($rootTableName, $parentPath=null, $tablesPrefix='', $relationshipOrClause=array(), $relationshipArgs=null, $usedAsCurrent=false)
	{
		$this->parentPath=$parentPath;
		$this->database=Database::$___defaultInstance;
		$this->databaseName=$this->database->getName();
		$this->tablesPrefix=$tablesPrefix;
		
	
		
		if($rootTableName)
		{
			$this->tableName=$rootTableName;
		}
		elseif(is_array($relationshipOrClause))
		{
			$fk=$this->getRelationshipFKLR($this->fkIndex=key($relationshipOrClause), $this->fkDirection=current($relationshipOrClause));
			$this->tableName=$fk[1][Database::IDX_FK_TABLE];
		}
		else
		{
			$this->tableName=$parentPath->tableName;
			$this->clause=$relationshipArgs;
			$this->clauseName=$relationshipOrClause;
		}
		
		$schema=$this->database->getRecordSchema($this->tableName, $this->tablesPrefix);
		
		$this->columns=$schema[Database::IDX_COLUMNS];
		$this->columnAliases=$schema[Database::IDX_COLUMN_ALIASES];
		
		foreach($this->columnAliases as $columnAlias=>$columnName)
		{
			$aliases[strtolower($columnAlias)]=$columnName;
		}
		$this->columnAliases=$aliases; 
		$this->usedAsCurrent=$usedAsCurrent;
		
		
		if(!isset(static::$___relationships[$this->databaseName]))
		{
			static::$___relationships[$this->databaseName]=$this->database->getRelationships(array('wfis'=>array()), array('wfis_tree' => 1, 'permissions' => 1), array('wfis_tree'=>1, 'wfis' => 1), 'wp_');
		}
		$this->idColumns=static::$___relationships[$this->databaseName]['tables'][$this->tableName][Database::IDX_ID_COLUMNS]['PRIMARY'];
		$currentParentPathDistance=0;
		for($parentPath=$this->parentPath; $parentPath; $parentPath=$parentPath->parentPath)
		{
			
			if($parentPath->usedAsCurrent)// || !$parentPath->parentPath)
			{
				break;
			}
			$currentParentPathDistance++;
		}
		$this->currentParentPath=$parentPath;
				
		$dataKey='';
		foreach($path=$this->getReversedPath() as $pathNode)
		{
			if($pathNode['fk'])
			{
				$dataKeyPart=$pathNode['fk_index'].'\\'.$pathNode['fk_direction'];
			}
			elseif($pathNode['clause_name'])
			{
			}
			else
			{
				$dataKeyPart=$pathNode['table'];
			}
			$dataKey.=($dataKey?'/':'').$dataKeyPart;
		}
		
		$this->currentParentPathDepth=count($path)-$currentParentPathDistance-1;
		if(!isset(static::$___sharedData[$dataKey]))
		{
			static::$___sharedData[$dataKey]=array();
			static::$___sharedDataLoaded=false;
		}
		$this->sharedData=&static::$___sharedData[$dataKey];
		$this->sharedDataLoaded=&static::$___sharedDataLoaded[$dataKey];
		
	}
	protected function getRelationshipFKLR($fkIndex, $fkDirection)
	{
		if(!isset(static::$___relationships[$this->databaseName]['fks'][$fkIndex]))
		{
			return null;
		}
		$fk=static::$___relationships[$this->databaseName]['fks'][$fkIndex];
		
		return array(
				0 => $fk[$fkDirection?0:1],
				1 => $fk[$fkDirection],
				Database::IDX_FK_ONE_2_ONE => $fk[Database::IDX_FK_ONE_2_ONE],
				Database::IDX_FK_DIRECTION => $fkDirection
			);
		
	}
	protected function getRelationship($relationshipAlias)
	{
		if(!isset(static::$___relationships[$this->databaseName]['tables'][$this->tableName][Database::IDX_RELATIONSHIPS_ALIASES][$relationshipAlias]))
		{
			$relationshipAlias=strtolower($relationshipAlias);
			if($relationshipAlias=='where' || $relationshipAlias=='orderby' || $relationshipAlias=='limit')
			{
				return $relationshipAlias;
			}
			return null;
		}
		
		$relationshipIndex=static::$___relationships[$this->databaseName]['tables'][$this->tableName][Database::IDX_RELATIONSHIPS_ALIASES][$relationshipAlias];
		$relationship=static::$___relationships[$this->databaseName]['tables'][$this->tableName][Database::IDX_RELATIONSHIPS][$relationshipIndex];
		
		return $relationship;
	}
	public function __get($relationshipAlias)
	{
		if(isset($this->columnAliases[$columnAlias=strtolower($relationshipAlias)]))
		{
			$current=current($this->dataCursor[0]);
			
			return $current[$this->columnAliases[$columnAlias]];
		}
		
		$isCurrentRecord=($relationshipAlias=='currentRecord');
				
		if($isCurrentRecord || $relationship=$this->getRelationship($relationshipAlias))
		{
			if($isCurrentRecord && $this->clauseName)
			{
				$relationship=$this->clauseName;
				$clause=$this->clause;
				$relationshipKey=$this->clauseName.'\\'.$this->clause;
			}
			else
			{
				if(!isset($relationship))
				{
					$relationship=array($this->fkIndex => $this->fkDirection);
				}
				$relationshipKey=key($relationship).'\\'.current($relationship);
				$clause='';
			}
			
			if(!$isCurrentRecord)
			{
				if(!isset($this->paths[$relationshipKey]))
				{
					$this->paths[$relationshipKey]=new static('', $this, $this->tablesPrefix, $relationship, $clause, false);
				}
				
				return $this->paths[$relationshipKey];
			}
			else
			{
				if(!$this->_currentRecord)
				{
					if($this->parentPath)
					{
						$this->_currentRecord=new static('', $this->parentPath, $this->parentPath->tablesPrefix, $relationship, $clause, true, $this);
					}
					else
					{
						$this->_currentRecord=new static($this->tableName, null, '', array(), null, true, $this);
				
					}
					$this->_currentRecord->data=&$this->data;
					$this->_currentRecord->dataCursor=&$this->dataCursor;
					$this->_currentRecord->loaded=&$this->loaded;
					$this->_currentRecord->eof=&$this->eof;
					
					if(!$this->loaded)
					{
						$this->moveFirst();
					}
				}
			
				return $this->_currentRecord;
			}
		
			
			
		}
		
		die(__METHOD__.': property not found "'.$relationshipAlias.'"');
	}
	
	/*navigation*/
	public function current()
	{
		return current($this->dataCursor[0]);
	}
	protected $null=array();
	public function moveFirst()
	{
		$this->load();
		
		if($this->currentParentPath)
		{
			$this->parentRPK=key($this->currentParentPath->dataCursor[0]);
		}
		
		if(is_null($this->parentRPK) || !isset($this->data[$this->parentRPK]))//!is_array($this->dataCursor[0]))
		{
			$this->dataCursor[0]=&$this->null;
			$this->eof=true;
			
			return;
		}
		if(!isset($this->data[$this->parentRPK]))
		{
			die('axxa');
		}
		$this->dataCursor[0]=&$this->data[$this->parentRPK];
		$this->eof=(current($this->dataCursor[0])===false);
	}
	public function moveNext()
	{
		$this->eof=(next($this->dataCursor[0])===false);
		
		$this->childPathsMoveFirst();
	}
	protected function childPathsMoveFirst()
	{
		if($this->paths)
		{
			foreach($this->paths as $path)
			{			
				$path->moveFirst();
			}
		}
		if($this->_currentRecord)
		{
			$this->_currentRecord->childPathsMoveFirst();
		}
	}
	public function moveLast()
	{
		if(!($this->eof=(end($this->dataCursor[0])===false)))
		{
			$this->childPathsMoveFirst();
		}
	}
	public function isEOF()
	{
		return $this->eof;
	}
	/**/
	protected function loadSharedData()
	{
		$query=$this->buildSqlSelect();
		$this->sharedData=$this->database->loadArrayRecordset($query, '____PATH____');
	}
	protected function load()
	{
		if($this->loaded)
		{
			return;
		}
		
		if(!$this->sharedDataLoaded)
		{
			$this->loadSharedData();
		}
		
		foreach($this->sharedData as $rpk=>$arrRecord)
		{
			$arr=explode('\\', $rpk);
			$parentRpk='';
			for($i=0; $i<$this->currentParentPathDepth; $i++)
			{
				$parentRpk.=($parentRpk?'\\':'').$arr[$i];
			}
			$this->data[$parentRpk][$rpk]=$arrRecord;
		}
		
		$this->loaded=true;
	}
	protected function getReversedPath()
	{
		for($parentPath=$this; $parentPath; $parentPath=$parentPath->parentPath)
		{
			$path[]=array('table' => $parentPath->tableName, 
				'clause' => $parentPath->clause, 
				'clause_name' => $parentPath->clauseName, 
				'fk' => $this->getRelationshipFKLR($parentPath->fkIndex, $parentPath->fkDirection),
				'id_columns' => $parentPath->idColumns,
				'fk_index' => $parentPath->fkIndex,
				'fk_direction' => $parentPath->fkDirection
				);
		}
		
		return array_reverse($path);
	}
	protected function buildSqlSelect($parentKeyLen=null)
	{
		$select=$query=$where=$selectPath='';
		$queryPathNodes=$this->getReversedPath();
		$numQueryPathNodes=$queryPathNodes;
		$rightTableNameAlias='';
		$j=0;
		$selectParentPathId='';
		foreach($queryPath=$queryPathNodes as $qpn/*$queryPathNode*/)
		{
			$j++;
			if($qpn['fk'])
			{
				$leftTableNameAlias=$qpn['fk'][0][Database::IDX_FK_TABLE];
				if(isset($joinedTables[$leftTableNameAlias]) && $joinedTables[$leftTableNameAlias]>0)
				{
					$leftTableNameAlias.='_alias_'.$joinedTables[$leftTableNameAlias];
				}
				if(isset($joinedTables[$qpn['table']]))
				{
					$joinedTables[$qpn['table']]++;
					$rightTableNameAlias=$qpn['table'].'_alias_'.$joinedTables[$qpn['table']];
					
				}
				else
				{
					$joinedTables[$qpn['table']]=0;
					$rightTableNameAlias='';
				}
				
			}
			if(($numIdColumns=count($qpn['id_columns']))==1)
			{
				$selectPathId='`'.($rightTableNameAlias?$rightTableNameAlias:$qpn['table']).'`.`'.key($qpn['id_columns']).'`';
			}
			else
			{
				$selectPathId='';
				foreach($qpn['id_columns'] as $idColumnName=>$autoIncrement)
				{
					$selectPathId.=($selectPathId?', \'-\', ':'').'`'.($rightTableNameAlias?$rightTableNameAlias:$qpn['table']).'`.`'.$idColumnName.'`';
				}
				$selectPathId='CONCAT('.$selectPathId.')';
			}
			
			
			
			$selectPath.=($selectPath?', \'\\\\\', ':'').$selectPathId;
			if($parentKeyLen && $j<=$parentKeyLen)
			{
				$selectParentPathId=$selectPath;
			}
			/*
			if($qpn['fk'])
			{
				$select.=($select?', '."\n\t":'').$qpn['table'].'.*';
			}
			*/
			if(!$query)
			{
				$query='`'.$qpn['table'].'`';
				$joinedTables[$qpn['table']]=0;
				continue;
			}
			if(!$qpn['fk'])
			{
				$where.=($where?' AND ':'').$qpn['clause'];
				continue;
			}
			
			$query.='INNER JOIN `'.$qpn['table'].'`'.($rightTableNameAlias?' AS '.$rightTableNameAlias:'')."\n\t".
				'ON ';
			
			
			for($i=0; $i<count($qpn['fk'][1][Database::IDX_FK_COLUMNS]); $i++)
			{
				$query.='`'.$leftTableNameAlias.'`.`'.$qpn['fk'][0][Database::IDX_FK_COLUMNS][$i].'`=`'.($rightTableNameAlias?$rightTableNameAlias:$qpn['table']).'`.`'.$qpn['fk'][1][Database::IDX_FK_COLUMNS][$i].'`';
			}
			
			$query.="\n";
		}
		
		$query='SELECT '.($selectParentPathId?'CONCAT('.$selectParentPathId.') AS ____PARENT_PATH____, '."\n\t":'').
					' CONCAT('.$selectPath.') AS ____PATH____, '."\n\t".
					'`'.($rightTableNameAlias?$rightTableNameAlias:$this->tableName).'`.* '."\n".
					'FROM '.$query.($where?"\n".'WHERE '.$where:'');
		
		return $query;
	}
	/*Clauses*/
	public function where($where)
	{
		if(!isset($this->paths['where\\'.$where]))
		{
			$this->paths['where\\'.$where]=new static('', $this, $this->tablesPrefix, 'where', $where);
		}
			
		return $this->paths['where\\'.$where];
	}
	public function orderBy($orderBy)
	{
	}
	public function limit($limit)
	{
	}
	
	/*debug methods*/
	public function &debugGetCurrentParentPath()
	{
		return $this->currentParentPath;
	}
	public function debugGetCurrentParentPathDepth()
	{
		return $this->currentParentPathDepth;
	}
	public function &debugGetSharedData()
	{
		return $this->sharedData;
	}
	public function debugGetUsedAsCurrent()
	{
		return $this->usedAsCurrent;
	}
}
