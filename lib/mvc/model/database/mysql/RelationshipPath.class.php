<?php

namespace alib\model;

include_once(__DIR__.'/Record.trait.php');

class RelationshipPath
{
	protected $mostRightTableName;
	static $___relationships;//@todo: rename to $___relationshipDefs
	static $___sharedData=array();
	
	const IDX_DATA='records'; //node records array
	const IDX_DATA2='data';//'data'; //node records array
	const IDX_LOADED='loaded'; //true if the data was loaded from database
	const IDX_CHILDREN='children'; //child nodes array
	
	const IDX_NODE_KEY='node_key'; //key of node entry in parent children array 
	const IDX_NODE_TYPE='type'; //0 - root node, 1 - FK node, 3 - clause node
	const IDX_REF_DATA_PARENT='parent_info'; //reference to parent node
	const IDX_NODE_RELATIONSHIP='relationship';
	const IDX_FK='fk'; //node FK; for clause nodes it's the FK of the first ascendant that has a FK
	const IDX_ROOT_TABLE_NAME='rootTableName'; //the table where the path starts
	
	protected $database, $databaseName, $tablesPrefix;
	protected $parentRecordWrapper, $parentRecordWrapperDepth;
	
	protected $pathLastIndex;
	protected $path; //array of refernces to nodes that creates the path
	protected $recordWrapperClassName='';
	
	static protected $___uq=1;
	static $___tableClassNames;
	
	public function __construct($parentRecordWrapper, $tablesPrefix='', $parentPath=null, $relationshipOrClause=array(), $relationshipArgs=null)//rentPath=null, $fkIndex=-1, $fkDirection=-1, $fkArgs='', $sqlWhere='', $sqlOrderBy='', $sqlLimit='')
	{
		
		$this->parentRecordWrapper=$parentRecordWrapper;
		$this->database=Database::$___defaultInstance;
		$this->databaseName=$this->database->getName();
		$this->tablesPrefix=$tablesPrefix;
		$this->recordWrapperClassName=get_class($parentRecordWrapper);
		
		if(!isset(static::$___relationships[$this->databaseName]))
		{
			static::$___relationships[$this->databaseName]=$this->database->getRelationships(array('wfis'=>array()), array('wfis_tree' => 1, 'permissions' => 1), array('wfis_tree'=>1, 'wfis' => 1), 'wp_');
		}
		
		if($parentPath)
		{
			if($parentPath->parentRecordWrapper!=$this->parentRecordWrapper)
			{
				$this->parentRecordWrapperDepth=count($parentPath->path)-1;
			}
			else
			{
				$this->parentRecordWrapperDepth=$parentPath->parentRecordWrapperDepth;
			}
			
			$this->path=$parentPath->path;
		}
		else
		{
			$this->parentRecordWrapperDepth=0;
		}
		if($this->parentRecordWrapper && !$parentPath)
		{
			$this->mostRightTableName=$this->parentRecordWrapper->getGenericTableName();
			$recordPathKey=$this->parentRecordWrapper->isPersistent()? implode('-', $this->parentRecordWrapper->getArrayRecordId2()): ('N-'.static::$___uq++);
			if(!isset(static::$___sharedData[$this->recordWrapperClassName][$this->mostRightTableName.'\\'.$recordPathKey]))
			{
				static::$___sharedData[$this->recordWrapperClassName][$this->mostRightTableName.'\\'.$recordPathKey]=array(
					static::IDX_DATA => array($recordPathKey=>$this->parentRecordWrapper),
					static::IDX_LOADED => 1,
					static::IDX_NODE_KEY => null, 
					static::IDX_CHILDREN => array(),
					static::IDX_REF_DATA_PARENT => null,
					static::IDX_FK => null,
					static::IDX_NODE_TYPE => 0,
					static::IDX_ROOT_TABLE_NAME => $this->mostRightTableName
					);
			}
			$this->parentRecordWrapper->rpk=$recordPathKey;
			
			$this->path[]=&static::$___sharedData[$this->recordWrapperClassName][$this->mostRightTableName.'\\'.$recordPathKey];
			
		}
		else
		{
			//$pathLen=count($this->path);
			
			$parentRefData=&$this->path[count($this->path)-1];
			if(!is_array($relationshipOrClause))
			{//WHERE, ORDER BY or LIMIT

				$infoKey=$relationshipOrClause.'\\'.$relationshipArgs;
		
				
				if(!isset($parentRefData[static::IDX_CHILDREN][$infoKey]))
				{
					$parentRefData[static::IDX_CHILDREN][$infoKey]=array(
																	static::IDX_DATA => array(), 
																	static::IDX_LOADED => false, 
																	static::IDX_NODE_KEY => $infoKey, 
																	static::IDX_REF_DATA_PARENT => &$parentRefData, 
																	static::IDX_CHILDREN => array(),
																	static::IDX_FK => $this->path[count($this->path)-1][static::IDX_FK],
																	static::IDX_NODE_TYPE => 3,
																	static::IDX_NODE_RELATIONSHIP => array($relationshipOrClause, $relationshipArgs)
																	);
				}
				
				$this->path[]=&$parentRefData[static::IDX_CHILDREN][$infoKey];

			}
			else
			{
				foreach($relationshipOrClause as $fkIndex=>$fkDirection)
				{
					$infoKey=$fkIndex.'\\'.$fkDirection;
					if(!isset($parentRefData[static::IDX_CHILDREN][$infoKey]))
					{
						$parentRefData[static::IDX_CHILDREN][$infoKey]=array(
																			static::IDX_DATA => array(), 
																			static::IDX_LOADED => false, 
																			static::IDX_NODE_KEY => $infoKey, 
																			static::IDX_REF_DATA_PARENT => &$parentRefData, 
																			static::IDX_CHILDREN => array(),
																			static::IDX_FK=>array($fkIndex, $fkDirection),
																			static::IDX_NODE_TYPE => 1,
																			static::IDX_NODE_RELATIONSHIP => array($fkIndex, $fkDirection)
																			);
					}
					
					
					$this->path[]=&$parentRefData[static::IDX_CHILDREN][$infoKey];
					$parentRefData=&$parentRefData[static::IDX_CHILDREN][$infoKey];
				}	
			}
			
			for($i=count($this->path)-1; $i>=0; $i--)
			{
				if($this->path[$i][static::IDX_NODE_TYPE]>1)
				{
					continue;
				}
				
				$fk=$this->getPathNodeFK($i);//$this->path[$i][static::IDX_FK][0], $this->path[$i][static::IDX_FK][1]);
				$this->mostRightTableName=$fk[1][Database::IDX_FK_TABLE];
				break;
			}
			
		}
		
		$this->pathLastIndex=count($this->path)-1;
		
	}
	public function getMostRightTableName()
	{
		return $this->mostRightTableName;
	}
	public function release()
	{
		$pathLen=$this->pathLastIndex;
		
		$this->path[$pathLen]=null;
		unset($this->path[$pathLen]);
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
	protected function getPathNodeFK($pathNodeIndex)
	{
		return $this->getRelationshipFKLR($this->path[$pathNodeIndex][static::IDX_FK][0], $this->path[$pathNodeIndex][static::IDX_FK][1]);
	}
	protected function getRelationshipFKFromKey($pathKey)
	{
		$pathKeyParts=explode('\\', $pathKey);
		
		if(count($pathKeyParts)!=2)
		{
			return;
		}
		
		return $this->getRelationshipFKLR($pathKeyParts[0], $pathKeyParts[1]);
	}
	public function __get($relationshipAlias)
	{
		if($relationship=$this->getRelationship($relationshipAlias))
		{
			return new static($this->parentRecordWrapper, $this->tablesPrefix, $this, $relationship, '');
		}
	}
	public function __call($relationshipAlias, $fkArgs)
	{
		$relationship=$this->getRelationship($relationshipAlias);
		if(!$relationship)
		{
			die(__METHOD__.': method "'.$relationshipAlias.'" does not exist');
		}
		return new static($this->parentRecordWrapper, $this->tablesPrefix, $this, $relationship, isset($fkArgs[0])?$fkArgs[0]:'');
	}
	protected function getRelationship($relationshipAlias)
	{
		if(!isset(static::$___relationships[$this->databaseName]['tables'][$this->mostRightTableName][Database::IDX_RELATIONSHIPS_ALIASES][$relationshipAlias]))
		{
			$relationshipAlias=strtolower($relationshipAlias);
			if($relationshipAlias=='where' || $relationshipAlias=='orderby' || $relationshipAlias=='limit')
			{
				return $relationshipAlias;
			}
			return null;
		}
		
		$relationshipIndex=static::$___relationships[$this->databaseName]['tables'][$this->mostRightTableName][Database::IDX_RELATIONSHIPS_ALIASES][$relationshipAlias];
		$relationship=static::$___relationships[$this->databaseName]['tables'][$this->mostRightTableName][Database::IDX_RELATIONSHIPS][$relationshipIndex];
		
		return $relationship;
	}
	public function notPersistentItems()
	{
		return $this->items(3);
	}
	public function persistentItems()
	{
		return $this->items(2);
	}
	public function items($itemsType=1)
	{
		$lastPathNodeInfo=&$this->path[$this->pathLastIndex];
		
		if(!$lastPathNodeInfo[static::IDX_LOADED] && $itemsType!=3)
		{
			$this->load();
		}
		$arrItems=array();
		if($this->parentRecordWrapper)
		{
			if(!isset($lastPathNodeInfo[static::IDX_DATA2][$this->parentRecordWrapper->rpk]))
			{
				//echo $this->parentRecordWrapper->rpk."<br>\n";
				$lastPathNodeInfo[static::IDX_DATA2][$this->parentRecordWrapper->rpk]=array();
				
				$cnt=substr_count($this->parentRecordWrapper->rpk, '\\');
				foreach($lastPathNodeInfo[static::IDX_DATA] as $recordPathKey=>&$recordWrapper)
				{
					
					$recordWrapper->relationshipPath=$this;

					$arr=explode('\\', $recordPathKey);
					$rpk2='';
					for($i=0; $i<=$cnt; $i++)
					{
						$rpk2.=($rpk2?'\\':'').$arr[$i];
					}
					
					$lastPathNodeInfo[static::IDX_DATA2][$rpk2][]=&$recordWrapper;
					
					//$lastPathNodeInfo[static::IDX_DATA2][$this->parentRecordWrapper->rpk][$recordPathKey]=$recordWrapper;
				}
				
				//foreach(array_keys($lastPathNodeInfo[static::IDX_REF_DATA_PARENT][static::IDX_DATA]) as $parentRpk)
				foreach(array_keys($this->parentRecordWrapper->relationshipPath->path[$this->parentRecordWrapper->relationshipPath->pathLastIndex][static::IDX_DATA]) as $parentRpk)
				{
					if(!isset($lastPathNodeInfo[static::IDX_DATA2][$parentRpk]))
					{
						$lastPathNodeInfo[static::IDX_DATA2][$parentRpk]=array();
					}
				}
				
			}
			
			$parentDataKeyLen=strlen($this->parentRecordWrapper->rpk);
			
			if($itemsType==1)
			{
				//echo 'da<br><hr>';
				return $lastPathNodeInfo[static::IDX_DATA2][$this->parentRecordWrapper->rpk];
			}
			
			foreach($lastPathNodeInfo[static::IDX_DATA2][$this->parentRecordWrapper->rpk] as $recordPathKey=>&$recordWrapper)
			{
				
				if(
					($itemsType==2 && $recordWrapper->isPersistent())
						|| ($itemsType==3 && !$recordWrapper->isPersistent()) 
						
					)
				{
					
					
					$arrItems[]=&$recordWrapper;
				}
			}
			//return $lastPathNodeInfo[static::IDX_DATA2][$itemsType][$this->parentRecordWrapper->rpk];
		}
		return $arrItems;
	}
	public function load()
	{
		$lastPathNodeInfo=&$this->path[$this->pathLastIndex];
		
		if($lastPathNodeInfo[static::IDX_LOADED])
		{
			return;
		}
		
		$query=$this->buildSqlSelect();
		
		if($arrRecordset=$this->database->loadArrayRecordset($query))
		{

			$startPathItem=$this->getPathStartAscendant()['loaded'];
			$startPathKeys=$this->getIdsPaths($this->path[$startPathItem][static::IDX_DATA]);
			
			
			$lastPathNodeData=&$lastPathNodeInfo[static::IDX_DATA];
			
			foreach($arrRecordset as $arrRecord)
			{
				$ID=$arrRecord['____PARENT_ID____'];
				$path=$arrRecord['____PATH____'];
				unset($arrRecord['____PARENT_ID____'], $arrRecord['____PATH____']);
				
				$lastPathNodeData[$startPathKeys[$ID].'\\'.$path]=$return=new $this->recordWrapperClassName( $arrRecord, $this, $startPathKeys[$ID].'\\'.$path, $this->mostRightTableName);;//$this->createRecordWrapper($arrRecord, $startPathKeys[$ID].'\\'.$path);
			}
		}
			
		$lastPathNodeInfo[static::IDX_LOADED]=true;	
	}
	protected function createRecordWrapper($arrRecord, $recordPathKey)
	{		
		$return=new $this->recordWrapperClassName( $arrRecord, $this, $recordPathKey, $this->mostRightTableName);
		
		$return->relationshipPath=$this;
		
		return $return;
	}
	
	public function getPathStartAscendant()
	{
		/* getPathStartAscendant este folosit pentru construirea SELECT-ului pentru incarcarea 
		 * ultimului nod din path
		 * 
		 * Trebuie sa caute primul nod ascendent (de la dreapta la stanga path-ului) care este incarcat
		 * Cheile array-ului care tine datele vor fi folosite in WHERE.
		 * 
		 * Cheile array-ului de date sint de forma:
		 * 
		 * id1\id2\id3\... 
		 * 
		 * Practic ultimul element din cheie (dupa ultimul "\") reprezinta id-ul dupa pk al inregistrarii pe care o tine daca inregistrarea este persistenta (salvata in bd) sau un 
		 * id uniqid() pentru inregistrari njoi adaugate cu addNew care nu au fost inca salvate on bd.
		 * 
		 * Pe langa primul ascendent incarcat, metoda trebuie si sa caute/determine din ce nod se incepe constructia SELECT-ului.
		 * 
		 * Trebuie tinut cont in toate astea de faptul ca un nod incarcat nu este neaparat unul care contine un fk, el poate contine clause (WHERE, ORDER BY sau LIMIT)
		 * 
		 * - gasirea primului ascendent incarcat e simpla, se cauta dupa $node[static::IDX_LOADED];
		 * - apoi se cauta, de la stanga la dreapta, incepand cu nodul incarcat, sa se caute primul nod care contine un fk;
		 *	fk-ul va arata in ce relatie (parent to child sau child to parent) se afla tabela incarcata si urmatoarea (din dreapta sa);
		 * 
		 *	Daca tabela din dreapta este child, atunci se incepe de la ea cu conditia ca coloanele din fk corespunzatoare tabelei din dreapta sa se regaseasca
		 *	printre cheile array-ului de date;
		 *	
		 *	Daca tabela din dreapta este parent, atunci query-ul trebuie incept de la tabela din stanga fk-ului cu conditia ca coloanele pk-ului tabelei din stanga 
		 *	sa fie printre chei (nu se mai folosesc campurile din fk)
		 * 
		 * - in cazul ca nu este gasit un nod urmator ascendentului incarcat care sa contina un fk (adica dupa tabela din care s-au incarcat inregistrari nu mai vine o alta ci doar clauze where, order by sau limit)
		 * atunci se cauta de la scendentul incarcat, de la dreapta spre stanga path-ului, fk-ul care leaga tabela precedenta de cea incarcata.
		 * JOIN-ul va incepe de la tabela din dreapta a fk-ului gasit dar conditia WHERE va fi pe pk-urile acestei tabele si nu pe coloanele fk-ului
		 *	
		 */
		
		//find first loaded path node; 
		for($loadedPathItem=$this->pathLastIndex; $loadedPathItem>=0; $loadedPathItem--)
		{
			if($this->path[$loadedPathItem][static::IDX_LOADED])
			{
				break;
			}
		}
		//find next path node that contains a fk
		$nextFKDirection=null;
		for($nextPathItem=$loadedPathItem+1; $nextPathItem<=$this->pathLastIndex; $nextPathItem++)
		{
			if($this->path[$nextPathItem][static::IDX_NODE_TYPE]>1)
			{
				continue;
			}
			
			$nextFKDirection=$this->path[$nextPathItem][static::IDX_FK][1];
			break;
			
		}
				
		//next fk not found, the last table in the path is the loaded one; the JOIN will start from it
		//find first ascendent starting with the loaded node that has an fk to find this last table name;
		//the join WHERE will contain a clause that this table PKs values are withing loaded node array keys
		if(is_null($nextFKDirection))
		{
			$nextFKDirection=1;
			for($i=$loadedPathItem; $i>=0; $i--)
			{
				if($this->path[$i][static::IDX_NODE_TYPE]>1)
				{
					continue;
				}
				$nextPathItem=$i;
				break;
			}
		}
		elseif($nextFKDirection==0)
		{
			for($i=$nextPathItem; $i<=$this->pathLastIndex; $i++)
			{
				if($this->path[$i][static::IDX_NODE_TYPE]>1)
				{
					continue;
				}

				$fk=$this->getPathNodeFK($i);
				
				if(!$fk[Database::IDX_FK_ONE_2_ONE])
				{
					break;
				}
				$nextPathItem=$i;
				$nextFKDirection=1;
			}
		}
		
		
		return array(
			'loaded' => $loadedPathItem,
			'query' => $nextPathItem,
			'direction' => $nextFKDirection
		);
	}
	
	
	protected function getIdsPaths($refDataData)
	{
		$startPathKeys=array();
		foreach($refDataData as $rpk=>$recordWrapper)
		{
			$id=$recordWrapper->getArrayRecordId2();
			if(!$id)
			{
				continue;
			}
			$id=implode('-', $id);
			$startPathKeys[$id]=$rpk;
		}
		return $startPathKeys;
	}
	public function buildSqlSelect()
	{
		$start=$this->getPathStartAscendant();
		
		$startPathItem=$start['loaded'];
		$startQueryNode=$start['query'];
		$direction=$start['direction'];
		/*
			Din cheile array-ului cu inregistrari se construieste un array de corespondente
			intre id-ul fiecarei inregistrari si cheia corespunzatoare. 
			Cheile reprezinta path-uri siid-ul este ultimul element in path
			Ex. user->orders
			O inregistrare cu cheia 1\3 inseamna orderul cu id 3 al userului cu id=1
		 */
		
		
		$startPathKeys=$this->getIdsPaths($this->path[$startPathItem][static::IDX_DATA]);
		//@todo if there are no startPathKeys (the records stored in the first "loaded" ascendant are not persistent)
		//then do not query the db
		if(!$startPathKeys)
		{
			//return '';
		}
				
		if($this->path[$this->pathLastIndex][static::IDX_DATA])
		{
			$loadedIDs=$this->getIdsPaths($this->path[$this->pathLastIndex][static::IDX_DATA]);
		}
		else
		{
			$loadedIDs=null;
		}
	
		$query='';
		
		$fkStartQuery=$this->getPathNodeFK($startQueryNode);
		$firstTableName=$fkStartQuery[$direction][Database::IDX_FK_TABLE];
				
		if($direction==0) //the query starts at the loaded table records or before it
		{			
			
			$joinedTableNames[$firstTableName]=1;
			$query=$firstTableName;
		}
				
		if($direction==0 || ($direction==1 && $startQueryNode<=$startPathItem)) //the query starts at the loaded table records or before it
		{
			$idColumnNames=array_keys(static::$___relationships[$this->databaseName]['tables'][$firstTableName][Database::IDX_ID_COLUMNS]['PRIMARY']);
		}
		else
		{
			$idColumnNames=$fkStartQuery[1][Database::IDX_FK_COLUMNS];
		}
		
		if(count($idColumnNames)>1)
		{	
			$sqlIDs='CONCAT(`'.$this->tablesPrefix.$firstTableName.'`.`'.implode('`, \'-\', `'.$this->tablesPrefix.$firstTableName.'`.`', $idColumnNames).'`)';
		}
		else
		{
			$sqlIDs='`'.$this->tablesPrefix.$firstTableName.'`.`'.current($idColumnNames).'`';
		}
				
		$sqlWhereIDs=$sqlIDs.(count($startPathKeys)==1?'=':' IN (').'\''.implode('\', \'', array_keys($startPathKeys)).'\''.(count($startPathKeys)==1?'':')');
		
		
		$sqlPath='';
		$idPathLen=0;
		$sqlWhere=$sqlOrderBy='';
		//echo 'aici:'.$startPathItem;
		for($i=$startQueryNode; $i<=$this->pathLastIndex; $i++)
		{
			if(isset($this->path[$i][static::IDX_ROOT_TABLE_NAME]))
			{
				$query='`'.$this->path[$i][static::IDX_ROOT_TABLE_NAME].'`';
				$joinedTableNames[$this->path[$i][static::IDX_ROOT_TABLE_NAME]]=1;
				continue;
			}
				
			switch(strval($this->path[$i][static::IDX_NODE_RELATIONSHIP][0]))
			{
				case 'where':
					$sqlWhere=$this->path[$i][static::IDX_NODE_RELATIONSHIP][1];
					break;
				case 'orderby':
					$sqlOrderBy=$this->path[$i][static::IDX_NODE_RELATIONSHIP][1];
					break;
				case 'limit':
					break;
				default: 
					$fk=$this->getPathNodeFK($i);
					$rightTableName=$fk[1][Database::IDX_FK_TABLE];
					$rightTableNameWithPrefix=$this->tablesPrefix.$rightTableName;
					
					$idColumns=static::$___relationships[$this->databaseName]['tables'][$rightTableName][Database::IDX_ID_COLUMNS]['PRIMARY'];
					$rightTableNameAlias='';
					
					if(!isset($joinedTableNames[$rightTableName]))
					{
						$joinedTableNames[$rightTableName]=0;
					}
					if(!$query)
					{
						$query='`'.$rightTableNameWithPrefix.'` ';
						$sqlIdColumns=count($idColumns)>1?'CONCAT(`'.$rightTableNameWithPrefix.'`.`'.implode('`, \'-\', `'.$rightTableNameWithPrefix.'`.`', array_keys($idColumns)).'`)':'`'.$rightTableNameWithPrefix.'`.`'.key($idColumns).'`';
					}
					else
					{
						$leftTableName=$fk[0][Database::IDX_FK_TABLE];
						
						if($joinedTableNames[$rightTableName]>0)
						{
							$rightTableNameAlias=$rightTableName.'_alias_'.$joinedTableNames[$rightTableName];
						}
						else
						{
							$rightTableNameAlias='';
						}
						
						if($joinedTableNames[$leftTableName]>1)
						{
							$leftTableNameAlias=$leftTableName.'_alias_'.($joinedTableNames[$leftTableName]-1);
						}
						else
						{
							$leftTableNameAlias='';
						}
						
						$query.=($query?' INNER JOIN ':'').'`'.$rightTableNameWithPrefix.'`'.($rightTableNameAlias?' AS `'.$rightTableNameAlias.'`':'');
						$joinCondition='';
						for($j=0; $j<count($fk[0][Database::IDX_FK_COLUMNS]); $j++)
						{
							$joinCondition.=($joinCondition?' AND ':'').'`'.($leftTableNameAlias?$leftTableNameAlias:$this->tablesPrefix.$leftTableName).'`.`'.$fk[0][Database::IDX_FK_COLUMNS][$j].'`=`'.($rightTableNameAlias?$rightTableNameAlias:$rightTableNameWithPrefix).'`.`'.$fk[1][Database::IDX_FK_COLUMNS][$j].'`';
						}
						$query.="\n\t".' ON '.$joinCondition.PHP_EOL;
						$t=$rightTableNameAlias?$rightTableNameAlias:$rightTableNameWithPrefix;
						

						if(count($idColumns)>1)
						{
							$sqlIdColumns='CONCAT(`'.$t.'`.`'.implode('`, \'-\', `'.$t.'`.`', array_keys($idColumns)).'`)';
						}
						else
						{
							$sqlIdColumns='`'.$t.'`.`'.key($idColumns).'`';
						}
						
					}
					
					$sqlPath.=($sqlPath?', \'\\\\\', ':'').$sqlIdColumns;
					$joinedTableNames[$rightTableName]++;
					$idPathLen++;
			}
			
		}
		
		if($loadedIDs)
		{
			$sqlLoadedIDs=(count($idColumns)==1?'`'.$this->mostRightTableName.'`.`'.key($idColumns).'`':'CONCAT('.$this->mostRightTableName.'.'.implode(', \'-\', '.$this->mostRightTableName.'.', array_keys($idColumns)).')').' NOT IN (\''.implode('\', \'', array_keys($loadedIDs)).'\')';
		}
		else
		{
			$sqlLoadedIDs='';
		}
		$sqlWhere2=$sqlWhere;
		if($sqlLoadedIDs)
		{
			$sqlWhere2=($sqlWhere2?'(':'').$sqlLoadedIDs.($sqlWhere2?') AND ('.$sqlWhere2.')':'');
		}
		
		if($sqlWhereIDs)
		{
			$sqlWhere2=($sqlWhere2?'(':'').$sqlWhereIDs.($sqlWhere2?') AND ('.$sqlWhere2.')':'');
		}
		
		$query= 'SELECT '.'`'.($rightTableNameAlias?$rightTableNameAlias:$rightTableNameWithPrefix).'`.*, '."\n\t".
				$sqlIDs.' AS `____PARENT_ID____`,'."\n\t".
				($idPathLen>1?'CONCAT(':'').$sqlPath.($idPathLen>1?')':'').' AS `____PATH____`'."\n".
				'FROM '.$query."\n".
				''.($sqlWhere2?' WHERE '.$sqlWhere2:'').
				($sqlOrderBy?'ORDER BY '.$sqlOrderBy:'');
		
		
		return $query;
	}
	
	public function add($record)
	{
		return $this->addNew($record);
	}
	public function set($record)
	{
		return $this->addNew($record, true);
	}
	public function addNew($arrRecord=array(), $overwriteExisting=false)
	{
		for($i=0; $i<=$this->pathLastIndex; $i++)
		{
			if($this->path[$i][static::IDX_NODE_TYPE]>1)
			{
				die(__METHOD__.': cannot add new record on a branch that contains WHERE, ORDER BY or LIMIT');
			}
		}
		/*
		 * Adaugare unui obiect se face doar la distanta de 1 fk fata de parentWrapper (pboectul record care "apeleaza" path-ul).
		 * Altfel nu s-ar putea determina ce obiecte se afla intre parentWrapper si cel nou adaugat.
		 * 
		 * Exista doar cateva exceptii:
		 * 
		 * - Cand, plecand de la parentWrapper prima relatie nu este one 2 one dar toate celelalte (incluzand si ultimul element al path-ului) sint one 2 one
		 * 
		 * - Sau, plecand de la parentWrapper, se ajunge la ultinul tabel al path-ului numai prin relatii one to one
		 *	Adica parentWrapper este in relatie one 2 one cu urmatoarea tabela care este la randul ei in relatie one to one cu urmatoarea
		 *	si tot asa pana la ultima tabela care nu mai este necesar sa fie one 2 one cu penultima;
		 * 
		 * - Sau, incepand de la parentWrapper pana la ultimul element al path-ului) path-ul are 3 elemente, tabela din mijloc fiind de legatura many - to -many inte prima si ultima;
		 * 
		 */
		
		
		
		$isOne2OnePath=true;
		
				
		$fk=$this->getPathNodeFK($this->parentRecordWrapperDepth+1);
		
		if($fk[Database::IDX_FK_ONE_2_ONE])
		{
			$one2OneRecordKey=substr($this->parentRecordWrapper->rpk, strrpos($this->parentRecordWrapper->rpk, '\\')+1);
			$lastFKMustBeOne2One=false;
			$k=0;
		}
		else
		{
			$one2OneRecordKey=('N-'.static::$___uq++);
			$lastFKMustBeOne2One=$this->pathLastIndex-$this->parentRecordWrapperDepth>1;
			$k=1;
		}
		
		for($i=$this->parentRecordWrapperDepth+1+$k; $i<$this->pathLastIndex; $i++)
		{
			if($this->path[$i][static::IDX_NODE_TYPE]!=1)
			{
				continue;
			}
			$fk=$this->getPathNodeFK($i);//$this->path[$i][static::IDX_FK][0], $this->path[$i][static::IDX_FK][1]);

			if(!$fk[Database::IDX_FK_ONE_2_ONE])
			{
				$isOne2OnePath=false;
				break;
			}
		}

		
		$recordPathKey=$this->parentRecordWrapper->rpk;
		
		if($isOne2OnePath)
		{
			for($i=$this->parentRecordWrapperDepth+1; $i<$this->pathLastIndex ;$i++)
			{
				$recordPathKey.='\\'.$one2OneRecordKey;
				if(!isset($this->path[$i][static::IDX_DATA][$recordPathKey]))
				{
					$this->path[$i][static::IDX_DATA][$recordPathKey]=array('[empty record]');
				}
			}
			
			$fk=$this->getPathNodeFK($this->pathLastIndex);
			
			$newRecordKey='';
			
			if($fk[Database::IDX_FK_ONE_2_ONE])
			{
				$newRecordKey=$one2OneRecordKey;
			}
			elseif(!$lastFKMustBeOne2One)
			{
				$newRecordKey=('N-'.static::$___uq++);
			}
		}
		
		$existingDataKey='';
		
		if(	( $fk[Database::IDX_FK_DIRECTION]==0 //direction is child -> parent; check if a parent record is already set
			||
				$fk[Database::IDX_FK_ONE_2_ONE]
			)
			&& count($this->path[$i][static::IDX_DATA])>0	)
		{
			$recordPathKeyLen=strlen($recordPathKey);
			
			foreach($this->path[$i][static::IDX_DATA] as $recordPathKey2=>$recordWrapper)
			{
				if(substr($recordPathKey2, 0, $recordPathKeyLen)==$recordPathKey)
				{
					if($overwriteExisting || !is_object($recordWrapper))
					{
						$existingDataKey=$recordPathKey2;
						break;
					}
					throw new \Exception('Cannot add "'.$fk[1][Database::IDX_FK_TABLE].'" table record for "'.$fk[0][Database::IDX_FK_TABLE].'" table. '."\n".'"'.$fk[1][Database::IDX_FK_TABLE].'" is the parent table and a record was already set. '."\n".'Use RelationshipPath::set to overwrite this record.'."\n");
				}
			}
			
		}
		if(!$newRecordKey)
		{
			die(__METHOD__.': cannot add new record');
		}
		$recordPathKey=$overwriteExisting && $existingDataKey? $existingDataKey: $recordPathKey.'\\'.$newRecordKey;
		$this->path[$i][static::IDX_DATA][$recordPathKey]=new $this->recordWrapperClassName( $arrRecord, $this, $recordPathKey, $this->mostRightTableName);//$this->createRecordWrapper($arrRecord, $recordPathKey);
		$this->path[$i][static::IDX_DATA2][$this->parentRecordWrapper->rpk][]=&$this->path[$i][static::IDX_DATA][$recordPathKey];
		$this->path[$i][static::IDX_DATA][$recordPathKey]->relationshipPath=$this;
		if(!$this->parentRecordWrapper->isPersistent())
		{
			$this->path[$i][static::IDX_LOADED]=true;
		}
		
		return $this->path[$i][static::IDX_DATA][$recordPathKey];		
	}
	public function visitPathData($callback, &$startNode=null, &$visitData=null)
	{
		if(!$startNode)
		{
			$startNode=&$this->path[0];
		}
		
		foreach($startNode[static::IDX_CHILDREN] as $pathKey=>$childRefData)
		{
			$fk=$childRefData[static::IDX_FK]?$this->getRelationshipFKLR($childRefData[static::IDX_FK][0], $childRefData[static::IDX_FK][1]):null;
			
			if(!$fk || $fk[Database::IDX_FK_DIRECTION]==1)
			{
				continue;
			}
			
			$this->visitPathData($callback, $startNode[static::IDX_CHILDREN][$pathKey], $visitData);
			
		}
		
		foreach($startNode[static::IDX_DATA] as $recordPathKey=>$recordWrapper)
		{
			if(!is_object($recordWrapper))
			{
				continue;
			}
			
			if(is_array($callback))
			{
				$callback[0]->$callback[1]($recordWrapper, $startNode, $visitData);
			}
			else
			{
				$callback($recordWrapper, $startNode, $visitData);
			}
		}
		
		foreach($startNode[static::IDX_CHILDREN] as $pathKey=>$childRefData)
		{
			$fk=$this->getRelationshipFKLR($childRefData[static::IDX_FK][0], $childRefData[static::IDX_FK][1]);
			
			if($fk[Database::IDX_FK_DIRECTION]!=1)
			{
				continue;
			}
			
			$this->visitPathData($callback, $startNode[static::IDX_CHILDREN][$pathKey], $visitData);
			
		}
	}
	public function saveRecordWrapper($wrapper, $refData, $visitData=array())
	{
		
		$parentRecordWrapper=$this->getParentWrapper($wrapper, $refData);
		
		$wrapper->save($visitData['update_keys']);
		
	}
	/*
	 * updateKeys123
	 * 
	 * Called by saveAll to update data tree array keys.
	 */
	public function updateKeys123(&$refData=null, $updateKeys=array())
	{
		//return;
		if(is_null($refData))
		{
			$refData=&$this->path[0];
			$updateRootKey=true;
		}
		else
		{
			$updateRootKey=false;
		}
		$updateKeys2=array();
		foreach($refData[static::IDX_DATA] as $recordPathKey=>$recordWrapper)
		{
			if($updateRootKey)
			{
				$rootDataKey=$recordWrapper->rpk;
			}
			$newDataKey='';
			if($updateKeys)
			{
				foreach($updateKeys as $replaceDataKey=>$replaceWithNewDataKey)
				{
					if(substr($recordPathKey, 0, $replaceDataKeyLen=strlen($replaceDataKey))==$replaceDataKey)
					{
						$newDataKey=$replaceWithNewDataKey.'\\'.substr($recordPathKey, $replaceDataKeyLen+1);
						break;
					}
				}
			}
			if(!$newDataKey)
			{
				$newDataKey=$recordPathKey;
			}
			if(!is_object($recordWrapper))/* 
											* 
											*/
			{
				$break=false;
				$recordPathKeyLen=strlen($recordPathKey);
				foreach($refData[static::IDX_CHILDREN] as $relationship=>$child)
				{
					foreach($child[static::IDX_DATA] as $childDataKey=>$childRecordWrapper)
					{
						if(substr($childDataKey, 0, $recordPathKeyLen)==$recordPathKey)
						{
							$backslashPos=strrpos($newDataKey, '\\');
							$newDataKey=substr($newDataKey, 0, $backslashPos).($backslashPos!==false?'\\':'').implode('-', $childRecordWrapper->getArrayrecordId2());
							
							$break=true;
							break;
						}
					}
				}
			}
			else
			{
				$backslashPos=strrpos($newDataKey, '\\');
				$newDataKey=substr($newDataKey, 0, $backslashPos).($backslashPos!==false?'\\':'').implode('-', $recordWrapper->getArrayRecordId2());
			}
			
			if($newDataKey==$recordPathKey)
			{
				//echo 'continue: '.$newDataKey;
				continue;
			}
			$updateKeys2[$recordPathKey]=$newDataKey;
			if(is_object($recordWrapper))
			{
				//echo 'new rpk:'.$newDataKey."\n";
				$recordWrapper->rpk=$newDataKey;
			}
			$refData[static::IDX_DATA][$newDataKey]=&$refData[static::IDX_DATA][$recordPathKey];
			unset($refData[static::IDX_DATA][$recordPathKey]);
			
			
		}
		
		foreach($refData[static::IDX_CHILDREN] as $relationship=>$child)
		{
			$this->updateKeys123($refData[static::IDX_CHILDREN][$relationship], $updateKeys2);
		}
		if($updateRootKey)
		{ 
			$rootDataKey=$this->path[0][static::IDX_ROOT_TABLE_NAME].'\\'.$rootDataKey;
			$newDataKey=$this->path[0][static::IDX_ROOT_TABLE_NAME].'\\'.implode('-', $recordWrapper->getArrayRecordId2());
			if($rootDataKey!=$newDataKey)
			{
				static::$___sharedData[$this->recordWrapperClassName][$newDataKey]=&static::$___sharedData[$this->recordWrapperClassName][$rootDataKey];
				unset(static::$___sharedData[$this->recordWrapperClassName][$rootDataKey]);
			}
		}
	}
	public function saveAll($useTransactions=false)
	{
		if($useTransactions)
		{
			$this->database->autocommit(false);
		}
		try
		{
			
			$this->visitPathData(array($this, 'saveRecordWrapper'), $null, $params);
			$this->updateKeys123();
			if($useTransactions)
			{
				$this->database->commit();
			}
		}
		catch(\Exception $err)
		{
			if($useTransactions)
			{
				$this->database->rollback();
			}
			throw $err;
		}
	}
	public function updateRecordDataKeys($recordWrapper, &$refData, &$visitData)
	{
		
		foreach($visitData as $replaceOldKey=>$replaceWithNewDataKey)
		{
			$replaceOldKeyLen=strlen($replaceOldKey);
			foreach($refData[static::IDX_DATA] as $recordPathKey=>$recordWrapper)
			{
				if(substr($recordPathKey, 0, $replaceOldKeyLen)==$replaceOldKey)
				{
					$newDataKey=$replaceWithNewDataKey.($replaceOldKeyLen<strlen($recordPathKey)?'\\'.substr($recordPathKey, $replaceOldKeyLen+1):'');
					
					if($recordWrapper->isPersistent() && ($basckslashPos=strrpos($newDataKey, '\\'))!==false)
					{
						$newDataKey=substr($newDataKey, 0,  $basckslashPos).'\\'.implode('-', $recordWrapper->getArrayrecordId());
					}
					
					$recordWrapper->rpk=$newDataKey;
					$refData[static::IDX_DATA][$newDataKey]=$refData[static::IDX_DATA][$recordPathKey];
					unset($refData[static::IDX_DATA][$recordPathKey]);
					$visitData[$recordPathKey]=$newDataKey;
					//echo 'Replace "'.$recordPathKey.'" with "'.$newDataKey."\n";
				}
			}
		}
	}
	
	/* @todo: trebuie redenumite cumva 2 "notiuni"
	 * - $this->parentRecordWrapper reprezinta recordul care a "apelat" calea.
	 *	De ex.:
	 * $product=$user->products->items()[0];
	 * 
	 * //in:
	 * $product->assortments->items();
	 * 
	 * //$this->parentRecordWrapper este $product
	 * 
	 * - metoda getParentWrapper insa avand un $assortment viziteaza structura de date in sus pana ajunge la products
	 *	si gaseste inregistrarea din products care id-ul in assorments (cauta chiar inregistrarea parent)
	 * 
	 * Un exemplu mai bun:
	 * 
	 * $product=$user->wfis->products->items()[0];
	 * 
	 * $wfi=$product->wfis->items()[0];
	 * 
	 * parentWrapper al caii $product->wfis este $product; parentWrapper al $wfi este $user (legatura dintr wfis si users este facut de id_user)
	 * 
	 */
	protected function getParentWrapper($recordWrapper, $refData)
	{
		$class=get_class($recordWrapper->record	);
		//echo 'axxa : '.$class."\n";
		if($class='alib\model\ContentTranslation')// && $wrapper->title=='2nd Level Category 13')
		{
			$arr=$recordWrapper->record->getArrayRecord();
			$rpk=$recordWrapper->rpk;
			if(isset($arr['title']) && $arr['title']=='2nd Level Category 13')
			{
				$i=0;
				//print_r($arr);
				//echo $class."\n";
			}
		}
		//$arr=$recordWrapper->record->getArrayRecord();
		//if(!$recordWrapper->parentRecordWrapper)
		{
			$parentRecordWrapper=null;
			$refData2=$refData;
			while($refData2 && $fk=$this->getRelationshipFKLR($refData2[static::IDX_FK][0], $refData2[static::IDX_FK][1]))
			{
				if($fk[1][Database::IDX_FK_TABLE]==$recordWrapper->record->getGenericTableName() && $fk[Database::IDX_FK_DIRECTION]==1)
				{
					print_r($fk);
					$refData2=$refData2[static::IDX_REF_DATA_PARENT];
					break;
				}
				$refData2=$refData2[static::IDX_REF_DATA_PARENT];
			}
			if(!$refData2)
			{
				return;
			}
			foreach($refData2[static::IDX_DATA] as $parentWrapperDataKey=>$parentRecordWrapper)
			{
				if(substr($recordWrapper->rpk, 0, strlen($parentWrapperDataKey)+1)==$parentWrapperDataKey.'\\')
				{
					break;
				}
			}
			
		}
		
		if(is_object($parentRecordWrapper))
		{
			for($i=0; $i<count($fk[0][Database::IDX_FK_COLUMNS]); $i++)
			{
				$recordWrapper->setFKColumnValues($fk, 1, $parentRecordWrapper);
				//$recordWrapper->record->{$fk[1][Database::IDX_FK_COLUMNS][$i]}=$parentRecordWrapper->record->{$fk[0][Database::IDX_FK_COLUMNS][$i]};
			}
		}
		
		//$break=false;
		$wrapperRecordPathKeyLen=strlen($recordWrapper->rpk);
		foreach($refData[static::IDX_CHILDREN] as $pathKey=>$child)
		{
			$fk=$this->getRelationshipFKLR($child[static::IDX_FK][0], $child[static::IDX_FK][1]);
			
			if($fk[Database::IDX_FK_DIRECTION]==1)
			{
				continue;
			}
			foreach($child[static::IDX_DATA] as $rightKey=>$childRecordWrapper)
			{
				if(substr($rightKey, 0, $wrapperRecordPathKeyLen)==$recordWrapper->rpk)
				{
					//$recordWrapper->childRecordWrapper=$parentRecordWrapper;
					//$recordWrapper->childRecordFK=$fk;
					
					for($i=0; $i<count($fk[0][Database::IDX_FK_COLUMNS]); $i++)
					{
						//$recordWrapper->record->{$fk[0][Database::IDX_FK_COLUMNS][$i]}=$childRecordWrapper->record->{$fk[1][Database::IDX_FK_COLUMNS][$i]};
						$recordWrapper->setFKColumnValues($fk, 0, $childRecordWrapper);
						//$break=true;
						break;
					}
				}
				//if($break)
				{
					//break;
				}
			}
		}
	}
	
	public function createRightRelationshipPath($record, $relationshipOrClause, $relationshipArgs)
	{
		if($relationship=$this->getRelationship($relationshipOrClause))
		{
			return new static($record, $this->tablesPrefix, $this, $relationship, $relationshipArgs);
		}
	}
	public function updateRecordWrapperDataKeys($recordWrapper)
	{
		$newDataKey=substr($recordWrapper->rpk, 0, $basckSlashPos=strrpos($recordWrapper->rpk, '\\')).($basckSlashPos!==false?'\\':'').implode('-', $recordWrapper->getArrayRecordId2());
			
			if($this->pathLastIndex==0)
			{
				static::$___sharedData[$this->recordWrapperClassName][$this->path[0][static::IDX_ROOT_TABLE_NAME].'\\'.$newDataKey]=
						&static::$___sharedData[$this->recordWrapperClassName][$this->path[0][static::IDX_ROOT_TABLE_NAME].'\\'.$recordWrapper->rpk];
				unset(static::$___sharedData[$this->recordWrapperClassName][$this->path[0][static::IDX_ROOT_TABLE_NAME].'\\'.$recordWrapper->rpk]);
			}
			$visitData[$recordWrapper->rpk]=$newDataKey;
			
			
			$this->visitPathData(array($this, 'updateRecordDataKeys' ), $this->path[$this->pathLastIndex], $visitData);
	}
	/*
	 * visitDataTreeRL
	 * 
	 * Visit the shared data structure, first current, next children, then next sibling
	 * 
	 */
	public function visitDataTreeRL($callback, &$sharedNode=null, &$visitData=null)
	{
		if(is_null($sharedNode))
		{
			$sharedNode=&$this->path[0];
		}
		
		$callback[0]->$callback[1]($sharedNode, $visitData);
		
		$visitData[static::IDX_CHILDREN]=array();
		if(isset($sharedNode[static::IDX_CHILDREN]))
		{
			foreach($sharedNode[static::IDX_CHILDREN] as $relationshipType=>$childNode)
			{
				$visitData[static::IDX_CHILDREN][$relationshipType]=array();
				$this->visitDataTreeRL($callback, 
						$sharedNode[static::IDX_CHILDREN][$relationshipType], 
						$visitData[static::IDX_CHILDREN][$relationshipType]);
			}
		}

	}
	public function visitDataTreeRL2($callback, &$sharedNode=null, $visitParams=null, &$visitData=null, $visitDataKey='', $visitDataFK='', &$parentNode=null)
	{
		if(is_null($sharedNode))
		{
			$sharedNode=&$this->path[0];
		}
		
		$callback[0]->$callback[1]($sharedNode, $visitData, $visitParams, $visitDataKey, $visitDataFK, $parentNode);
		
		$visitData[static::IDX_CHILDREN]=array();
		if(isset($sharedNode[static::IDX_CHILDREN]))
		{
			foreach($sharedNode[static::IDX_CHILDREN] as $relationshipType=>$childNode)
			{
				$visitData[static::IDX_CHILDREN][$relationshipType]=array();
				$this->visitDataTreeRL2($callback, 
						$sharedNode[static::IDX_CHILDREN][$relationshipType], 
						$visitParams,
						$visitData[static::IDX_CHILDREN][$relationshipType],
						$relationshipType,
						preg_match('|^[0-9]+\\\\[01]{1}$|', $relationshipType) ? $relationshipType : $visitDataFK, 
						$sharedNode);
			}
		}
	}
	public function importDataTreeNode($node, $params, &$sharedNode=null, $visitDataKey='', $visitDataFK='', &$parentSharedNode=null, $overwriteRootWrapper=false)
	{
		if(is_null($sharedNode))
		{
			$sharedNode=&$this->path[0];
			$nodeType=0;
		}
		else
		{
			$nodeType=$visitDataKey==$visitDataFK ? 1 : 3;
		}
		if(!$visitDataFK)
		{
			$tableName=$this->path[0][static::IDX_ROOT_TABLE_NAME];
		}
		else
		{
			$fk=$this->getRelationshipFKFromKey($visitDataFK);
			$tableName=$fk[1][Database::IDX_FK_TABLE];
		}
		
		foreach($params as $key=>$val)
		{
			$sharedNode[$key]=$val;
		}
		if($visitDataFK)
		{
			$arr=explode('\\', $visitDataFK);
			$sharedNode[static::IDX_FK]=array($arr[0], $arr[1]);
		}
		else
		{
			$sharedNode[static::IDX_FK]=null;
		}
		$sharedNode[static::IDX_NODE_KEY]=$visitDataKey;
		$sharedNode[static::IDX_NODE_TYPE]=$nodeType;
		$sharedNode[static::IDX_REF_DATA_PARENT]=&$parentSharedNode;
		
		if(count($arr=explode('\\', $visitDataFK))==2)
		{
			$sharedNode[static::IDX_NODE_RELATIONSHIP]=array($arr[0], $arr[1]);
		}
		
		if(!isset($sharedNode[RelationshipPath::IDX_DATA]))
		{
			$sharedNode[RelationshipPath::IDX_DATA]=array();
		}
		if(isset($node[RelationshipPath::IDX_DATA]))
		{
			
			foreach($node[RelationshipPath::IDX_DATA] as $recordRPK=>$arrRecord)
			{
				//echo $recordRPK."\n";
				
				if($overwriteRootWrapper && $nodeType==0 && $sharedNode[RelationshipPath::IDX_DATA])
				{
					//echo 'da: '.$recordRPK."\n";
					$key=key($sharedNode[RelationshipPath::IDX_DATA]);
					if($key!=$recordRPK)
					{
						//$sharedNode[RelationshipPath::IDX_DATA][$recordRPK]
						$sharedNode[RelationshipPath::IDX_DATA][$recordRPK]=&$sharedNode[RelationshipPath::IDX_DATA][$key];
						$sharedNode[RelationshipPath::IDX_DATA][$recordRPK]->rpk=$recordRPK;
						//echo '$key: '.$key."\n\n\n";
						unset($sharedNode[RelationshipPath::IDX_DATA][$key]);
					}
					//die('axxa'.$recordRPK);
				}
				
				if(!isset($sharedNode[RelationshipPath::IDX_DATA][$recordRPK]))
				{
					//echo 'aicisha'.$this->recordWrapperClassName;
					//print_r($arrRecord);
					$sharedNode[RelationshipPath::IDX_DATA][$recordRPK]=new $this->recordWrapperClassName( $arrRecord, $this, $recordRPK, $tableName);//$this->createRecordWrapper($arrRecord, $recordRPK);//$record->getArrayRecord();//$record->tableName;
				}
				else
				{
					foreach($arrRecord as $columnName=>$columnValue)
					{
						try
						{
							//echo get_class($sharedNode[RelationshipPath::IDX_DATA][$recordRPK]->record)."\n";
							$sharedNode[RelationshipPath::IDX_DATA][$recordRPK]->$columnName=$columnValue;
						}
						catch(\Exception $err)
						{
							//echo $err->getCode();
							//die('23');
						}
					}
					//die('axxa');
				}
			}
		}
		
		
		if(isset($node[static::IDX_CHILDREN]))
		{
			foreach($node[static::IDX_CHILDREN] as $childRelationshipType=>$childNode)
			{
				if(!isset($sharedNode[static::IDX_CHILDREN][$childRelationshipType]))
				{
					$sharedNode[static::IDX_CHILDREN][$childRelationshipType]=array();
				}
				
				$childSharedNode=&$sharedNode[static::IDX_CHILDREN][$childRelationshipType];
				
				$this->importDataTreeNode($node[static::IDX_CHILDREN][$childRelationshipType], $params, $childSharedNode, $childRelationshipType, 
						
						preg_match('|^[0-9]+\\\\[01]{1}$|', $childRelationshipType) ? $childRelationshipType : $visitDataFK, $sharedNode);
			}
		}
		if(!isset($sharedNode[static::IDX_CHILDREN]))
		{
			$sharedNode[static::IDX_CHILDREN]=array();
		}
	}
	public function add2ExportSource($node, &$visitData)
	{
		
		$visitData[RelationshipPath::IDX_DATA]=array();
		foreach($node[RelationshipPath::IDX_DATA] as $recordRPK=>$record)
		{
			$visitData[RelationshipPath::IDX_DATA][$recordRPK]=$record->getArrayRecord();//$record->tableName;
		}
		
		
	}
	
	public function import($source, $params, $overwriteRootWrapper)
	{
		$this->importDataTreeNode($source, $params, $null, $null, $null, $null, $overwriteRootWrapper);
		
	}
	public function export(&$source)
	{
		return $this->visitDataTreeRL(array($this, 'add2ExportSource'), $null, $source);
	}
	/* debug methods*/
	public function debugGetPath()
	{
		return $this->path;
	}
	public function debugPrintData($arr=null, $printRecords=true)
	{
		if(is_null($arr))
		{
			$arr=static::$___sharedData;
			$print=true;
		}
		else
		{
			$print=false;
		}
		$data=array();
		foreach($arr as $key=>$val)
		{
			if(is_array($val) || is_object($val))
			{
				continue;
			}
			$data[$key]=$val;			
		}
		foreach($arr as $key=>$val)
		{
			if(!is_object($val) )
			{
				continue;
			}
			$data[$key]=$val->getArrayRecord();
			$data[$key]['rpk']=$val->rpk;
			$data[$key]['class']=get_class($val);
			$data[$key]['table']=$val->getGenericTableName();
		}
		foreach($arr as $key=>$val)
		{
			if(!is_array($val) || $key==static::IDX_REF_DATA_PARENT)
			{
				continue;
			}
			$data[$key]=$this->debugPrintData($val);
		}
		if(!$print)
		{
			return $data;
		}
		
		print_r($data);
	}
	public function debugGetPathStartAscendant()
	{
		return $this->getPathStartAscendant();
	}
	static public function ___debugReleaseSharedData($includeFirstLevel=false)
	{
		
		{
			foreach(static::$___sharedData as $className=>$classNameSharedData)
			{
				foreach($classNameSharedData as $rootTableKey=>$rootTableData)
				{
					static::$___sharedData[$className][$rootTableKey][static::IDX_CHILDREN]=array();
				}
			}
		}
		
		if($includeFirstLevel)
		{
			foreach(static::$___sharedData as $className=>$classNameSharedData)
			{
				static::$___sharedData[$className]=array();
			}
		}
		
	}
	
	
}

class RelationshipRecordWrapper
{
	public $record, $relationshipPath;
	public $rpk;//$recordPathKey
	
		
	public function __construct($record, $relationshipPath=null, $rpk='', $tableName='')
	{
		
		$this->record=is_object($record)?$record:$this->createRecord($record, $tableName);
		if(!$relationshipPath)
		{
			//it also sets $this->rpk;
			$this->relationshipPath=$this->createRelationshipPath();
		}
		else
		{
			$this->rpk=$rpk;
		}
	}
	protected function createRecord($arrRecord, $tableName)
	{
		$className=$this->getTableClassName($tableName);
		
		if($arrRecord)
		{
			$idColumns=RelationshipPath::$___relationships[Database::$___defaultInstance->getName()]['tables'][$tableName][Database::IDX_ID_COLUMNS]['PRIMARY'];
		
			foreach($idColumns as $idColumnName=>$extra)
			{
				$arrId[$idColumnName]=isset($arrRecord[$idColumnName])?$arrRecord[$idColumnName]:null;
			}
		}
		else
		{
			$arrId=null;
		}
		
		$record=new $className($arrRecord, $tableName);
		
		//@todo check if validation (and the commented block below) is needed
		/* 
		if($arrRecord)
		{
			foreach($arrRecord as $columnName=>$columnValue)
			{
				try
				{
					$record->$columnName=$columnValue;
				}
				catch(\Exception $err)
				{
				}
			}
		}
		 * 
		 */
		return $record;
	}
	protected function getTableClassName($genericTableName)
	{
		$className='';
		if(Database::$___defaultInstance->getName()=='trendyflendy.ro')
		{
			switch($genericTableName)
			{
				case 'content_items':
					$className='ContentItem';
					break;
				case 'content_translations':
					$className='ContentTranslation';
					break;
				case 'content_details_translations':
					$className='ContentDetailsTranslation';
					break;
				case 'content_images':
					$className='ContentImage';
					break;
				case 'content_images_translations':
					$className='ContentImageTranslation';
					break;
				case 'users':
					$className='User';
					break;
			}
		}
		else
		if(Database::$___defaultInstance->getName()=='wordpress')
		{
			$className='';
			switch($genericTableName)
			{
				case 'users':
					$className='WPUser';
					break;
				case 'posts':
					$className='WPPost';
					break;
				case 'postmeta':
					$className='WPPostMeta';
					break;
				
			}
		}
		else
		{
			switch($genericTableName)
			{
				case 'wfis_translations':
					$className='WFITranslation';
					break;
				case 'products':
					$className='Product';
					break;
				case 'assortments':
					$className='Assortment';
					break;
				case 'assortments_attributes':
					$className='AssortmentAttribute';
					break;
				case 'orders':
					$className='Order';
					break;
				case 'orders_assortments':
					$className='OrderAssortment';
					break;
				case 'wfis':
					$className='WFI';
					break;
				case 'wfis_translations_bodies':
					$className='WFITranslationBody';
					break;
				case 'permissions':
					$className='Permission';
					break;
				case 'groups':
					$className='Group';
					break;
				case 'users':
					$className='User';
					break;
				case 'wfis_images':
					$className='Image';
					break;
				case 'wfis_images_translations':
					$className='ImageTranslation';
					break;
				case 'posts':
					$className='WPPost';
					break;
				case 'wfis_tree':
					$className='WFITreeNode';
					break;
			}
		}
		if($className)
		{
			return $className;
		}
		
		die('Cannot find class name for table `'.Database::$___defaultInstance->getName().'`.`'.$genericTableName.'`');
	}
	protected function createRelationshipPath()
	{
		return new RelationshipPath($this, $this->record->getTableNamePrefix());
	}
	public function __get($propertyName)
	{
		if($this->relationshipPath && $return=$this->relationshipPath->createRightRelationshipPath($this, $propertyName, ''))
		{
			return $return;
		}	
		return $this->record->$propertyName;
	}
	public function __set($propertyName, $propertyValue)
	{
		$this->record->$propertyName=$propertyValue;
	}
	public function saveAll($useTransactions=false)
	{
		$this->relationshipPath->saveAll($useTransactions);
	}
	public function __call($methodName, $methodArgs)
	{
		if($this->relationshipPath && $return=$this->relationshipPath->createRightRelationshipPath($this, $methodName, isset($methodArgs[0])?$methodArgs[0]:''))
		{
			return $return;
		}
				
		switch(count($methodArgs))
		{
			case 0:
				return $this->record->$methodName();
			case 1:
				return $this->record->$methodName($methodArgs[0]);
			case 2:
				return $this->record->$methodName($methodArgs[0], $methodArgs[1]);
			case 3:
				return $this->record->$methodName($methodArgs[0], $methodArgs[1], $methodArgs[2]);
		}
		return call_user_func_array(array($this->record, $methodName), $methodArgs);
	}
	public function save($updateKeys=true)
	{
		$this->record->save();
		
		if($updateKeys)
		{
			$this->relationshipPath->updateRecordWrapperDataKeys($this);			
		}
	}
	public function getArrayRecordId2()
	{
		return $this->record->getArrayRecordId();
	}
	public function setFKColumnValues($fk, $fkDirection, $linkedRecordWrapper)
	{
		static $cnt=0;
		
		for($i=0; $i<count($fk[0][Database::IDX_FK_COLUMNS]); $i++)
		{
				//echo $fk[1][Database::IDX_FK_COLUMNS][$i].'='.$fk[0][Database::IDX_FK_COLUMNS][$i]."\n";
				//$recordWrapper->setFKColumnValues($fk, 1, $parentRecordWrapper);
			$this->record->{$fk[$fkDirection][Database::IDX_FK_COLUMNS][$i]}=$linkedRecordWrapper->{$fk[$fkDirection==1?0:1][Database::IDX_FK_COLUMNS][$i]};
		}
	}
}