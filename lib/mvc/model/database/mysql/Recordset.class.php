<?php

namespace alib\model;

class Recordset
{
	protected $database, $databaseName, $tablesPrefix;
	public $data, $loaded, $dataCursor;
	public $eof;
	protected $tableName, $idColumns;
	protected $parentPath, $currentParentPath;
	protected $clause, $clauseName, $fkIndex, $fkDirection;
	static $___relationships;
	protected $paths;
	protected $usedAsCurrent=false;
	protected $columns, $columnAliases;
	
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
		
		for($parentPath=$this->parentPath; $parentPath; $parentPath=$parentPath->parentPath)
		{
			if($parentPath->usedAsCurrent)
			{
				break;
			}
		}
		$this->currentParentPath=$parentPath;
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
		
		if($relationshipAlias=='currentRecord')
		{
			$prefix='@';
			$path=&$this->parentPath;
			
		}
		else
		{
			$prefix='';
			$path=&$this;
		}
		if($prefix || $relationship=$this->getRelationship($relationshipAlias))
		{
			if($prefix && $this->clauseName)
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
			
			$prefixedRelationshipKey=$prefix.$relationshipKey;
			
			if(!isset($path->paths[$prefixedRelationshipKey]))
			{
				$path->paths[$prefixedRelationshipKey]=new static('', $path, $path->tablesPrefix, $relationship, $clause, $prefix?true:false);
				
				if($prefix)
				{
					if(!isset($path->paths[$relationshipKey]) || !$path->paths[$relationshipKey]->loaded || current($path->paths[$relationshipKey]->data)===false) 
					{
						die(__METHOD__.': cannot use "current" for a '.(!isset($path->paths[$relationshipKey])?'non-existing':(!$path->paths[$relationshipKey]->loaded?'not loaded':'')).' path');
					}
				
					$path->paths[$prefixedRelationshipKey]->dataCursor=&$this->dataCursor;
					$path->paths[$prefixedRelationshipKey]->loaded=true;
				}
			}
			
			return $path->paths[$prefixedRelationshipKey];
		}
		
		die(__METHOD__.': property not found "'.$relationshipAlias.'"');
	}
	public function __call($methodName, $methodArgs)
	{
	}
	/*navigation*/
	public function current()
	{
		return current($this->dataCursor[0]);
	}
	public function moveFirst()
	{
		$this->load();
		
		if($this->currentParentPath)
		{
			$this->dataCursor[0]=&$this->data[key($this->currentParentPath->dataCursor[0])];
		}
		else
		{
			$this->dataCursor[0]=&$this->data;
		}
		if(!is_array($this->dataCursor[0]))
		{
			$this->eof=true;
			return;
		}
		reset($this->dataCursor[0]);
		if(!($this->eof=current($this->dataCursor[0])===false))
		{
			$arr=current($this->dataCursor[0]);
			foreach($this->columnAliases as $columnAlias=>$columnName)
			{
				$this->$columnAlias=$arr[$columnName];
			}
		}
	}
	public function moveNext()
	{
		if(!($this->eof=(next($this->dataCursor[0])===false)))
		{
			$arr=current($this->dataCursor[0]);
			foreach($this->columnAliases as $columnAlias=>$columnName)
			{
				$this->$columnAlias=$arr[$columnName];
			}
		}
	}
	public function moveLast()
	{
	}
	public function isEOF()
	{
		return $this->eof;
	}
	/**/
	protected function load()
	{
		if($this->loaded)
		{
			return;
		}
		$parentPathLen=$this->currentParentPath?substr_count(key($this->currentParentPath->dataCursor[0]), '\\')+1:0;
		$query=$this->buildSqlSelect($parentPathLen);
		
		if($this->currentParentPath)
		{
			$this->data=$this->database->loadArrayRecordset($query, '____PARENT_PATH____', '____PATH____');
			
			//foreach($arrRecordset as $recordPathKey=>$arrRecord)
			{
				//$this->data[$arrRecord['____PARENT_PATH____']][$recordPathKey]=$arrRecord;
			}
		}
		else
		{
			$this->data=$this->database->loadArrayRecordset($query, '____PATH____');
			//$this->dataCursor[0]=&$this->data;
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
				'id_columns' => $parentPath->idColumns);
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
	
	
}
