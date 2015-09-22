<?php

namespace alib\model;


class DatabaseSchema extends Database
{
	protected $fks, $tableIDColumns, $tableFKs, $redundantFKs, $relationships;
	protected $dbInformationSchema;
	protected $tableNamesPrefix;
	
	
	public function __construct($name, $hostName, $userName, $password, $tableNamesPrefix, $port=3306, $socket=null)
	{
		parent::__construct($name, $hostName, $userName, $password, $port, $socket);
		
		$this->dbInformationSchema=new Database('information_schema', 'localhost', 'root', '');
		$this->tableNamesPrefix=$tableNamesPrefix;
		
		$this->fks=$this->loadFKs();
		
		$this->tableFKs=$this->getTableFKs();
		$this->redundantFKs=$this->getRedundantFKs();
		
		$this->tableIDColumns=$this->loadTablesIdColumns();
		
		foreach($this->fks as $i=>$fk)
		{
			$this->fks[$i][static::IDX_FK_ONE_2_ONE]=$this->isFkOne2One($fk);
		}
		$this->relationships=$this->loadRelationships($skipTablesOne2One=array(), $skipTables=array(), $backReferencedTables=array(), $this->tableNamesPrefix);
		
		if($tableNamesPrefix)
		{
			$prefixLen=strlen($tableNamesPrefix);
			foreach($this->relationships['fks'] as $fkIndex=>$fk)
			{
				for($i=0; $i<=1; $i++)
				{
					if(substr($fk[$i][Database::IDX_FK_TABLE], 0, $prefixLen)==$tableNamesPrefix)
					{
						$this->relationships['fks'][$fkIndex][$i][Database::IDX_FK_TABLE]=substr($fk[$i][Database::IDX_FK_TABLE], $prefixLen);
					}
				}
			}
		}
		//print_r($this->tableFKs); die();
		//static::$___relationships[$this->name]=;
		//print_r(static::$___relationships[$this->name]);
		//@fixme: de revazut chestia asta cu Database si DatabaseSchema
		//poate ar trebui redenumita DatabaseSchema in DebugDatabase si in modul DEBUG
		//sa se lucreze cu ea (fata de Database stie doar sa aduca info din information_schema iar in mod non-DEBUG Database
		//o sa aduca info din fisiere de pe disc)
		
		//$configCacheFilename=CONFIG_CACHE_DIR.'/database/relationships.inc.php';
		//file_put_contents($configCacheFilename, '<?php'."\n".'static::$___relationships='.var_export(static::$___relationships, true).';');
	}
	public function getRecordSchema($tableName, $tableNamePrefix='')
	{
		static $dbInformationSchema=null;
		
		if(!$dbInformationSchema)
		{
			$dbInformationSchema=new Database('information_schema', 'localhost', 'root', '');
		}
		
		//$tableName=$tableNamePrefix.$tableName;
		
		$recordSchema[static::IDX_COLUMNS]=$this->getRecordColumns($dbInformationSchema, $tableNamePrefix.$tableName);
		$recordSchema[static::IDX_COLUMN_ALIASES]=$this->getRecordColumnAliases($recordSchema[static::IDX_COLUMNS]);
		$recordSchema[static::IDX_ID_COLUMNS]=$this->tableIDColumns[$tableNamePrefix.$tableName];
		$recordSchema[static::IDX_ID_AUTOINCREMENT_COLUMN]='';
		foreach($recordSchema[static::IDX_ID_COLUMNS] as $indexName=>$indexColumns)
		{
			foreach($indexColumns as $indexColumnName=>$indexColumnExtra)
			{
				if($indexColumnExtra)
				{
					$recordSchema[static::IDX_ID_AUTOINCREMENT_COLUMN]=$indexColumnName;
					break;
				}
			}
		}
		if(isset(static::$___relationships[$this->name]['tables'][$tableName][static::IDX_RELATIONSHIPS_ALIASES]))
		{
			$recordSchema[static::IDX_RELATIONSHIPS_ALIASES]=static::$___relationships[$this->name]['tables'][$tableName][static::IDX_RELATIONSHIPS_ALIASES];
		}
		if(isset($this->relationships['tables'][$tableName][static::IDX_RELATIONSHIPS_ALIASES]))
		{
			$recordSchema[static::IDX_RELATIONSHIPS_ALIASES]=$this->relationships['tables'][$tableName][static::IDX_RELATIONSHIPS_ALIASES];
		}
		//$recordSchema[static::IDX_FKS]=$this->getRecordFKs($dbInformationSchema, $this->name, $tableName);
		
		//$recordSchema[static::IDX_IDS]=$this->getRecordIdIndexes($dbInformationSchema, $this->name, $tableName, $recordSchema[static::IDX_COLUMNS]);
		
		//$recordSchema[static::IDX_VERSION_FKS]=$this->getRecordVersionFKs($dbInformationSchema, $this->name, $tableName, $recordSchema[static::IDX_FKS]);
		
		return $recordSchema;
	}
	public function getRelationships($skipTablesOne2One=array(), $skipTables=array(), $backReferencedTables=array(), $tablesPrefix='')
	{
		return $this->relationships;
	}
	public function getRecordColumns($dbInformationSchema, $tableName)
	{
		
		static $dbTypes=null;
		
		if(!$dbTypes)
			$dbTypes=array(		
			'binary' => array(static::IDX_TYPE => static::TYPE_BINARY),
			'bit' => array(static::IDX_TYPE => static::TYPE_BIT),
			'blob' => array(static::IDX_TYPE => static::TYPE_BLOB), 
			'char' => array(static::IDX_TYPE => static::TYPE_STRING), 
			'date' => array(static::IDX_TYPE => static::TYPE_DATE, static::IDX_MIN_VALUE => '1000-01-01', static::IDX_MAX_VALUE => '9999-12-31'),
			'datetime' => array(static::IDX_TYPE => static::TYPE_DATETIME, static::IDX_MIN_VALUE => '1000-01-01 00:00:00', static::IDX_MAX_VALUE => '9999-12-31 23:59:59'),
			'decimal' => array(static::IDX_TYPE => static::TYPE_DECIMAL),
			'float' => array(static::IDX_TYPE => static::TYPE_FLOAT, static::IDX_MIN_VALUE => -3.402823466E+38, static::IDX_MAX_VALUE => 3.402823466E+38),
			'double' => array(static::IDX_TYPE => static::TYPE_FLOAT, static::IDX_MIN_VALUE => -1.7976931348623157E+308, static::IDX_MAX_VALUE => -2.2250738585072014E-308),
			'int' => array(static::IDX_TYPE => static::TYPE_INTEGER, static::IDX_MIN_VALUE => -2147483648, static::IDX_MAX_VALUE => 2147483647), 
			'longblob' => array(static::IDX_TYPE => static::TYPE_BLOB),
			'longtext' => array(static::IDX_TYPE => static::TYPE_STRING), 
			'mediumblob' => array(static::IDX_TYPE => static::TYPE_BLOB), 
			'mediumint' => array(static::IDX_TYPE => static::TYPE_INTEGER, static::IDX_MIN_VALUE => -8388608, static::IDX_MAX_VALUE => 8388607), 
			'mediumtext' => array(static::IDX_TYPE => static::TYPE_STRING), 
			'set' => array(static::IDX_TYPE => static::TYPE_STRING),
			'enum' => array(static::IDX_TYPE => static::TYPE_STRING),
			'smallint' => array(static::IDX_TYPE => static::TYPE_INTEGER, static::IDX_MIN_VALUE => -32768, static::IDX_MAX_VALUE => 32767), 
			'text' => array(static::IDX_TYPE => static::TYPE_STRING), 
			'time' => array(static::IDX_TYPE => static::TYPE_TIME, static::IDX_MIN_VALUE => '-838:59:59', static::IDX_MAX_VALUE => '838:59:59'),
			'tinyblob' => array(static::IDX_TYPE => static::TYPE_BLOB), 
			'tinytext' => array(static::IDX_TYPE => static::TYPE_STRING), 
			'tinyint' => array(static::IDX_TYPE => static::TYPE_INTEGER, static::IDX_MIN_VALUE => -128, static::IDX_MAX_VALUE => 127), 
			'timestamp' => array(static::IDX_TYPE => static::TYPE_TIMESTAMP, static::IDX_MIN_VALUE => '1970-01-01 00:00:01', static::IDX_MAX_VALUE => '2038-01-01 00:00:01'),
			'varbinary' => array(static::IDX_TYPE => static::TYPE_BINARY),
			'varchar' => array(static::IDX_TYPE => static::TYPE_STRING),
			'year' => array(static::IDX_TYPE => static::TYPE_INTEGER, static::IDX_MIN_VALUE => 1901, static::IDX_MAX_VALUE => 2155),
			'bigint' => array(static::IDX_TYPE => static::TYPE_INTEGER, static::IDX_MIN_VALUE => -9223372036854775808, static::IDX_MAX_VALUE => 9223372036854775807),
		);
		
		$query='SELECT * FROM COLUMNS WHERE TABLE_SCHEMA=\''.$this->name.'\'
			AND TABLE_NAME=\''.$tableName.'\'';
		
		foreach($columnsInfo=$dbInformationSchema->loadArrayRecordset($query) as $columnInfo)
		{
			$columns[$columnInfo['COLUMN_NAME']]=array();
			$column=&$columns[$columnInfo['COLUMN_NAME']];
			
			//generic data type (string, integer, float etc
			$column[static::IDX_TYPE]=$dbTypes[$columnInfo['DATA_TYPE']][static::IDX_TYPE];
			
			//min and max values
			
			$isUnsigned=(strpos(strtolower($columnInfo['COLUMN_TYPE']), 'unsigned')!==false);
			
			if(isset($dbTypes[$columnInfo['DATA_TYPE']][static::IDX_MIN_VALUE]))
			{
				$column[static::IDX_MIN_VALUE]=$isUnsigned?0:$dbTypes[$columnInfo['DATA_TYPE']][static::IDX_MIN_VALUE];
			}
			
			if(isset($dbTypes[$columnInfo['DATA_TYPE']][static::IDX_MAX_VALUE]))
			{
				$column[static::IDX_MAX_VALUE]=$isUnsigned?$dbTypes[$columnInfo['DATA_TYPE']][static::IDX_MAX_VALUE]*2+1: $dbTypes[$columnInfo['DATA_TYPE']][static::IDX_MAX_VALUE];
			}
			
			//precision for numeric values
			if($columnInfo['NUMERIC_PRECISION'])
			{
				$column[static::IDX_DIGITS]=$columnInfo['NUMERIC_PRECISION']-$columnInfo['NUMERIC_SCALE'];
				$column[static::IDX_DECIMALS]=$columnInfo['NUMERIC_SCALE'];
			}
			
			//strings, enum, set max length
			if($columnInfo['CHARACTER_MAXIMUM_LENGTH'])
			{
				$column[static::IDX_MAX_LENGTH]=$columnInfo['CHARACTER_MAXIMUM_LENGTH'];
			}
			
			//default value
			$column[static::IDX_DEFAULT]=$columnInfo['COLUMN_DEFAULT'];
			
			//options for enum and set columns		
			if(strtolower($columnInfo['DATA_TYPE'])=='enum' 
				|| strtolower($columnInfo['DATA_TYPE'])=='set')
			{
				preg_match('/\(([^$]+)\)$/', $columnInfo['COLUMN_TYPE'], $matches);
				$options=array();
				foreach(explode(',', $matches[1]) as $quotedOption)
				{
					$options[substr($quotedOption, 1, -1)]=substr($quotedOption, 1, -1);
				}
				
				
				
				$column[static::IDX_OPTIONS]=$options;
				
			}
			
			//comment
			$column[static::IDX_COMMENT]=$columnInfo['COLUMN_COMMENT'];
				
			//@todo
			//add DATETIME_PRECISION info
			
			$flags=0;
			
			$flags=strtolower($columnInfo['IS_NULLABLE'])=='yes'?0:static::FLAG_NOT_NULL;
			if(strtolower($columnInfo['DATA_TYPE'])=='set')
			{
				$flags |= static::FLAG_MULTIPLE_OPTIONS;
			}
			if($isUnsigned)
			{
				$flags |= static::FLAG_UNSIGNED;
			}
			
			$column[static::IDX_FLAGS]=$flags;
			
		}
		
		return $columns;
	}
	public function getRecordColumnAliases($columns)
	{
		foreach($columns as $columnName=>$columnInfo)
		{
			$aliases[$columnName]=$columnName;
			
			$underscorePos=0;
			$columnAlias=$columnName;
			if($underscorePos+2>strlen($columnAlias))
			{
				$aliases[$columnAlias]=$columnName;
				continue;
			}
			while($underscorePos+2<strlen($columnAlias) && ($underscorePos=strpos($columnAlias, '_', $underscorePos+2))!==false)
			{
				$columnAlias=substr($columnAlias, 0, $underscorePos).strtoupper($columnAlias[$underscorePos+1]).substr($columnAlias, $underscorePos+2);
			}
			
			if($columnAlias!=$columnName)
			{
				$aliases[$columnAlias]=$columnName;
			}
		}
		
		return $aliases;
	}
	/**
	 * Returns information about a table foreign keys
	 * 
	 * @param type $dbInformationSchema
	 * @param type $tableName
	 */
	
	/*
	
	public function getRecordFKs($dbInformationSchema, $dbName, $tableName)
	{
		$fullTableName=str_replace('.', '@002e', $dbName).'/'.$tableName;
		
		$fks=array();
		
		$query='SELECT *,
				IF(information_schema.INNODB_SYS_FOREIGN.FOR_NAME=\''.$fullTableName.'\', FALSE, TRUE) AS is_referenced
			FROM information_schema.INNODB_SYS_FOREIGN 
			WHERE information_schema.INNODB_SYS_FOREIGN.FOR_NAME=\''.$fullTableName.'\'
				OR information_schema.INNODB_SYS_FOREIGN.REF_NAME=\''.$fullTableName.'\'';
		
		if($fksInfo=$this->loadArrayRecordset($query, 'ID'))
		{	
			$query='SELECT *
					FROM information_schema.INNODB_SYS_FOREIGN_COLS
					WHERE information_schema.INNODB_SYS_FOREIGN_COLS.ID IN(\''.implode('\',\'', array_keys($fksInfo)).'\')';
			$fksColumnsInfo=$this->loadArrayRecordset($query);
			
			foreach($fksColumnsInfo as $fkColumnsInfo)
			{
				$ID=$fkColumnsInfo['ID'];
				
				$isReferenced=$fksInfo[$ID]['is_referenced'];
				$fkTableFullName=$isReferenced?$fksInfo[$ID]['FOR_NAME']:$fksInfo[$ID]['REF_NAME'];
				$fkTableName=substr($fkTableFullName, strpos($fkTableFullName, '/')+1);
				
				$fkName=substr($ID, strpos($ID, '/')+1);
				
				
				if(($colonPos=strpos($fkName, ':'))!==false)
				{
					//if foreign key has a form as prop:prop2
					if($isReferenced)
					{
						$fkName=substr($fkName, 0, $colonPos);
					}
					else
					{
						$fkName=substr($fkName, $colonPos+1);
					}
				}
				else
				{
					$fkName=$fkTableName;
				}
				
				if(isset($fks[$fkName]['ID']) && $fks[$fkName]['ID']!=$ID)
				{
					die(__METHOD__.': foreign key "'.$fkName.'" already set');
				}
				$fks[$fkName][static::IDX_FK_TABLE]=$fkTableName;
								
				if($isReferenced)
				{
					$fks[$fkName][static::IDX_FK_COLUMNS][$fkColumnsInfo['REF_COL_NAME']]=$fkColumnsInfo['FOR_COL_NAME'];
				}
				else
				{
					$fks[$fkName][static::IDX_FK_COLUMNS][$fkColumnsInfo['FOR_COL_NAME']]=$fkColumnsInfo['REF_COL_NAME'];
				}
				
				$fkTableColumns=$this->getRecordColumns($dbInformationSchema, $fkTableName);
				$fkIdIndexes=$this->getRecordIdIndexes($dbInformationSchema, $dbName, $fkTableName, $fkTableColumns);
				
				if(!isset($fkIdIndexes['PRIMARY']))
				{
					
					die(__METHOD__.': table "'.$tableName.'" foreign key table ("'.$fkTableName.'" has no primary key');
				}
				$fks[$fkName][static::IDX_FK_TABLE_PK]=$fkIdIndexes['PRIMARY'];
				
				$fks[$fkName]['ID']=$ID;
			}
			
			foreach($fks as &$fk)
			{
				unset($fk['ID']);
			}
		}
		
		return $fks;
	}
	 * 
	 */
	/*
	 * Returns info about indexes that may uniquely identify a record (primary keys and not null unique columns)
	 * 
	 */
	/*
	public function getRecordIdIndexes($dbInformationSchema, $dbName, $tableName, $tableColumns)
	{
		$indexes=$unsetIndexes=array();
		
		$query='SELECT information_schema.INNODB_SYS_INDEXES.NAME AS index_name,
				information_schema.INNODB_SYS_INDEXES.TYPE,
				information_schema.INNODB_SYS_FIELDS.NAME AS column_name
			FROM information_schema.INNODB_SYS_TABLES INNER JOIN  information_schema.INNODB_SYS_INDEXES
				USING(TABLE_ID)
			INNER JOIN information_schema.INNODB_SYS_FIELDS
				USING(INDEX_ID)
			WHERE information_schema.INNODB_SYS_TABLES.NAME=\''.$this->getEscapedName().'/'.$tableName.'\'
				';
		
		if ($indexesRecordset=$this->loadArrayRecordset($query))
		{
			foreach($indexesRecordset as $indexRecord)
			{
				if($indexRecord['TYPE']!=2 && $indexRecord['TYPE']!=3)
				{
					continue;
				}
				
				//if one column of a unique index can be null then it cannot be an ID index (uniquely idetify a record)
				if($indexRecord['TYPE']==2 && !($tableColumns[$indexRecord['column_name']][static::IDX_FLAGS]&static::FLAG_NOT_NULL))
				{
					$unsetIndexes[$indexRecord['index_name']]=$indexRecord['index_name'];
				}
				$indexes[$indexRecord['index_name']][$indexRecord['column_name']]=$indexRecord['column_name'];
			}
			
			
		}
		if($unsetIndexes)
		{
			foreach($unsetIndexes as $unsetIndex)
			{
				unset($indexes[$unsetIndex]);
			}
		}
		return $indexes;
	}
	 * 
	 */
	/*
	public function getRecordVersionFKs($dbInformationSchema, $dbName, $tableName, $tableFKs)
	{
		$versionFKs=array();
		
		foreach($tableFKs as $fkName=>$tableFK)
		{
			$fkLinkedTableName=$tableFK[static::IDX_FK_TABLE];
			$fkLinkedTableColumns=$this->getRecordColumns($dbInformationSchema, $fkLinkedTableName);
			$fkLinkedTableIndexes=$this->getRecordIdIndexes($dbInformationSchema, $dbName, $fkLinkedTableName, $fkLinkedTableColumns);
			
			foreach($fkLinkedTableIndexes as $fkLinkedTableIndexName=>$fkLinkedTableIndexInfo)
			{
				if(count($fkLinkedTableIndexInfo)<=count($tableFK[static::IDX_FK_COLUMNS]))
				{
					continue;
				}
				$isVersion=true;
				foreach($tableFK[static::IDX_FK_COLUMNS] as $fkColumnName=>$fkOtherTableColumnName)
				{
					if(!isset($fkLinkedTableIndexInfo[$fkOtherTableColumnName]))
					{
						$isVersion=false;
						break;
					}
				}
				if($isVersion)
				{
					foreach($fkLinkedTableIndexInfo as $linkedTableColumnName)
					{
						if(!in_array($linkedTableColumnName, $tableFK[static::IDX_FK_COLUMNS]))
						{
							$versionColumns[$linkedTableColumnName]=$linkedTableColumnName;
						}
					}
					$tableFK[static::IDX_VERSION_COLUMNS]=$versionColumns;
					
					$versionFKs[$fkName]=$tableFK;
				}
			}
		}
		return $versionFKs;
	}
	 * 
	 */
	/**
	 * getRedundantFKs
	 * 
	 * Cauta printre fk-urile dintre tabele acele fk-uri care fac legatura intre aceleasi tabele.
	 * De exemplu, intr-o bd. cu o tabela orders care tine doar idurile pentru adresele de livrare si de billing,
	 * exista 2 fk-uri care leaga orders de addresses.
	 * 
	 * Este utila atunci cand se construiesc alias-urile pentru caile prin care se poate ajunge de la o tabela la alta:
	 * 
	 * users -> orders -> addresses (via id_shipping_address)
	 * users -> orders -> addresses (via id_billing_address)
	 * 
	 * 
	 * @param type $tableConnections
	 */
	protected function getRedundantFKs()
	{
		$redundantFKs=array();
		foreach($this->fks as $fkName=>$fk)
		{
			if(isset($tableLinks[$fk[0][static::IDX_FK_TABLE]][$fk[1][static::IDX_FK_TABLE]]))
			{
				$redundantFKs[$fkName]=$fkName;
				$redundantFKs[$tableLinks[$fk[0][static::IDX_FK_TABLE]][$fk[1][static::IDX_FK_TABLE]]]=$tableLinks[$fk[0][static::IDX_FK_TABLE]][$fk[1][static::IDX_FK_TABLE]];
			}
			else
			{
				$tableLinks[$fk[0][static::IDX_FK_TABLE]][$fk[1][static::IDX_FK_TABLE]]=$fkName;
			}
		}
		return $redundantFKs;
	}
	public function loadRelationships($skipTablesOne2One=array(), $skipTables=array(), $backReferencedTables=array(), $tablesPrefix='', $debugFormat=false)
	{	
		

		$paths=$this->buildTableRelationshipPaths($skipTablesOne2One, $skipTables, $backReferencedTables);
		
		foreach($this->tableIDColumns as $tableName=>$idColumns)
		{
			$paths[$tableName][static::IDX_ID_COLUMNS]=$idColumns;
		}
		
		
		foreach($this->tableIDColumns as $tableName=>$idColumns)
		{
			$columns2=array();
			foreach($idColumns as $indexName=>$columns)
			{
				if(count($columns)<2)
				{
					continue;
				}
				
				$columns2=$columns;
				foreach($columns as $columnName=>$autoIncrement)
				{
					if($autoIncrement)
					{
						unset($columns2[$columnName]); //skip auto_increment columns
						continue;
					}
					$break=false;
					foreach($this->tableFKs[$tableName] as $fkName=>$direction)
					{
						$fk=$this->fks[$fkName];
						if(!$direction) //the table is the parent , skip the fk
						{
							continue;
						}
						foreach($fk[1][static::IDX_FK_COLUMNS] as $fkColumnName)
						{
							if($fkColumnName==$columnName)
							{
								unset($columns2[$columnName]); //skip auto_increment columns
								$break=true;
								break;
							}
						}
						if($break)
						{
							break;
						}
						
					}
				}
			}
			if(!$columns2)
			{
				continue;
			}
			//echo $tableName.' : ';//.$indexName.':';
			$versionColumns[$tableName]=$columns2;
			//print_r($columns2);
			//echo "\n------------\n";
		}
		foreach($this->tableFKs as $tableName=>$fks)
		{
			if(!isset($versionColumns[$tableName]))
			{
				continue;
			}
			foreach($fks as $fkName=>$direction)
			{
			
				$fk=$this->fks[$fkName];
				
				if(!$this->isFkOne2One($fk))
				{
					continue;
				}
				
				
				$otherTable=$fk[$direction?0:1][static::IDX_FK_TABLE];
				
				foreach($versionColumns[$tableName] as $versionColumnName=>$extra)
				{
					for($i=0; $i<count($fk[$direction][static::IDX_FK_COLUMNS]); $i++)
					{
						if($fk[$direction][static::IDX_FK_COLUMNS][$i]!=$versionColumnName)
						{
							continue;
						}
						$otherVersionColumn=$fk[$direction?0:1][static::IDX_FK_COLUMNS][$i];
						foreach($this->tableIDColumns[$otherTable] as $indexName=>$indexColumns)
						{
							$break=false;
							foreach($indexColumns as $indexColumn=>$extra)
							{
								if($indexColumn==$otherVersionColumn)
								{
									$versionColumns[$otherTable][$otherVersionColumn]=$extra;
									$break=true;
									break;
								}
							}
							if($break)
							{
								break;
							}
						}
						
					}
				}
				
				//echo $otherTable."\n";
			}
		}
		foreach($paths as $tableName=>$path)
		{
			$paths[$tableName][static::IDX_VERSION_COLUMNS]=isset($versionColumns[$tableName])?$versionColumns[$tableName]:array();
		}
		
		if(!$debugFormat)
		{
			$fks=$this->fks;
			$fks2=array();
			$prefixLen=strlen($tablesPrefix);
			foreach($fks as $fkName=>$fk)
			{
				for($i=0; $i<=1; $i++)
				{
					if(substr($fk[$i][Database::IDX_FK_TABLE], 0, $prefixLen)==$tablesPrefix)
					{
						//$fk[$i][Database::IDX_FK_TABLE]=substr($fk[$i][Database::IDX_FK_TABLE], $prefixLen);
						//$fk[$i]['has_prefix']=true;
					}
				}
				
				$fkIndex=count($fks2);
				$fks2[$fkIndex]=$fk;//+array(3=>$fkName);
				$fks[$fkName][3]=$fkIndex;
			}
			foreach($paths as $tableName=>$path)
			{
				if(substr($tableName, 0, $prefixLen)==$tablesPrefix)
				{
					$tableName2=substr($tableName, $prefixLen);
				}
				else
				{
					$tableName2=$tableName;
				}
				$paths2[$tableName2][static::IDX_RELATIONSHIPS]=array();
				if(!isset($path[static::IDX_RELATIONSHIPS]))
				{
					continue;
				}
				if($path[static::IDX_RELATIONSHIPS])
				{
					foreach($path[static::IDX_RELATIONSHIPS] as $relationships)
					{
						$path2Index=count($paths2[$tableName2][static::IDX_RELATIONSHIPS]);
						foreach($relationships as $fkName=>$direction)
						{
							$paths2[$tableName2][static::IDX_RELATIONSHIPS][$path2Index][$fks[$fkName][3]]=$direction;
						}
					}

				}
				$paths2[$tableName2][static::IDX_RELATIONSHIPS_ALIASES]=$paths[$tableName][static::IDX_RELATIONSHIPS_ALIASES];
				$paths2[$tableName2][static::IDX_ID_COLUMNS]=$paths[$tableName][static::IDX_ID_COLUMNS];
				$paths2[$tableName2][static::IDX_VERSION_COLUMNS]=$paths[$tableName][static::IDX_VERSION_COLUMNS];
			}
			
			
			return array( 'fks' => $fks2, 'tables' => $paths2 );
		}
		return array( 'fks' => $this->fks, 'tables' => $paths );
	}
	protected function isFkOne2One($fk)
	{
		for($i=0; $i<=1; $i++)
		{
			$tableName=$fk[$i][static::IDX_FK_TABLE];

			$fkColumns=$fk[$i][static::IDX_FK_COLUMNS];
			sort($fkColumns);

			foreach($this->tableIDColumns[$tableName] as $indexName=>$indexColumns)
			{
				$indexColumns=array_keys($indexColumns);
				sort($indexColumns);

				if($fkColumns!=$indexColumns)
				{
					return false;
				}
			}
		}
		
		
		return true;
		
	}
	protected function isFkEndOnId($fk, $parentEnd)
	{
		$fkEnd=$parentEnd?0:1;
		
		$tableName=$fk[$fkEnd][static::IDX_FK_TABLE];

		$fkColumns=$fk[$fkEnd][static::IDX_FK_COLUMNS];
		sort($fkColumns);
			
		foreach($this->tableIDColumns[$tableName] as $indexName=>$indexColumns)
		{
			$indexColumns=array_keys($indexColumns);
			sort($indexColumns);
			
			if($indexColumns==$fkColumns)
			{
				return true;
			}
		}
		return false;
	}
	protected function isFkMany2Many($fk)
	{
		$childTableName=$fk[1][static::IDX_FK_TABLE];
		
		if(count($this->tableFKs[$childTableName])!=2)//child table in fk  must have exactly 2 foreign keys
		{
			return false;
		}
		
		foreach($this->tableFKs[$childTableName] as $childFKName=>$isChild)
		{
			if(!$isChild)//in both keys the child table must be child :D
			{
				return false;
			}
			if($this->fks[$childFKName]!=$fk)
			{
				$fk2=$this->fks[$childFKName];
			}
		}
		
		//child columns of both fks must compose an id column in the child table
		$allFKChildColumns=array_merge($fk[1][static::IDX_FK_COLUMNS], $fk2[1][static::IDX_FK_COLUMNS]);
		sort($allFKChildColumns);		
		
		$childIdInFK=false;
		foreach($this->tableIDColumns[$childTableName] as $indexName=>$indexColumns)
		{
			$indexColumns=array_keys($indexColumns);
			sort($indexColumns);
			
			if($allFKChildColumns==$indexColumns)
			{
				$childIdInFK=true;
			}
		}
		if(!$childIdInFK)
		{
			return false;
		}
		
		//both fks parent columns must be id columns in parent table
		if(!$this->isFkEndOnId($fk, true) //parent table id columns must be all part of the fk
				|| !($this->isFkEndOnId($fk2, true)) )//
		{
			return false;
		}
		return true;
		
	}
	protected function sortPathsByCount($path1, $path2)
	{
		return count($path1)>count($path2);
	}
	protected function sortPathsByDistance($stopTablePaths1, $stopTablePaths2)
	{
		return count(reset($stopTablePaths1))>count(reset($stopTablePaths2));
	}
	protected function sortRelationshipPaths($paths)
	{
		foreach($paths as $startTable=>&$startTablesPaths)
		{
			foreach($startTablesPaths as $stopTable=>&$stopTablePath)
			{
				usort($stopTablePath, array($this, 'sortPathsByCount'));
			}
		}
		
		foreach($paths as $startTable=>&$startTablesPaths)
		{
			uasort($startTablesPaths, array($this, 'sortPathsByDistance'));
		}
		return $paths;
	}
	protected function getWordPlural($word)
	{
		switch(substr($word, -1))
		{
			case 'y':
				return substr($word, 0, -1).'ies';
				break;
			case 's':
				return $word.'es';
				break;
			default:
				return $word.'s';
		}
	}
	/**
	 * buildFKAlias
	 * 
	 * 
	 * 
	 * @param type $fkName
	 * @param type $isChild
	 * @param type $tableName
	 * @param type $fullAlias	if true and ":" separator is found then both left and right parts of the $fkName is used to build the alias
	 *							(see getRedundantFKs)
	 * @param type $plural
	 * @return type
	 */
	protected function buildFKAlias($fkName, $isChild, $tableName, $fullAlias)
	{
		
		$colonPos=strpos($fkName, ':');
		
		$fkNameLeftPart=$isChild?substr($fkName, $colonPos+1):substr($fkName, 0, $colonPos);
		
		if(($commaPos=strpos($fkNameLeftPart, ','))!==false)
		{
			$leftAliases[]=substr($fkNameLeftPart, $commaPos+1);
			$leftAliases[]=substr($fkNameLeftPart, 0, $commaPos);
		}
		else
		{
			$leftAliases[]=$fkNameLeftPart;
			$leftAliases[]=$this->getWordPlural($fkNameLeftPart);
		}
		
		if($fullAlias && $isChild)
		{
			$fkNameRightPart=!$isChild?substr($fkName, $colonPos+1):substr($fkName, 0, $colonPos);
		
			if(($commaPos=strpos($fkNameRightPart, ','))!==false)
			{
				$rightAliases[]=substr($fkNameRightPart, $commaPos+1);
				$rightAliases[]=substr($fkNameRightPart, 0, $commaPos);
			}
			else
			{
				$rightAliases[]=$fkNameRightPart;
				$rightAliases[]=$this->getWordPlural($fkNameRightPart);
			}
			
			foreach($leftAliases as $alias)
			{
				foreach($rightAliases as $alias2)
				{
					$aliases[]=$isChild ?  $alias2.'_'.$alias : $alias.'_'.$alias2;
				}
			}
			
			
		
			//$alias.=':'.$isChild;
		}
		else
		{
			$aliases=$leftAliases;
		}
		
		
		return $aliases;
		
	}
	protected function buildJavaStyleId($str)
	{
		if($arrUnderscores=explode('_', $str))
		{
			$strNoUnderscore='';
			foreach($arrUnderscores as $strPart)
			{
				$strNoUnderscore.=$strNoUnderscore?ucfirst($strPart):$strPart;
			}
					
		}
				
		return $strNoUnderscore;
	}
	protected function buildRelationshipPathAliases($tablePaths)
	{
		$paths=array();
		
		foreach($tablePaths as $startTable=>&$startTablesPaths)
		{
			$aliasesBuilt=array();
			
			foreach($startTablesPaths as $stopTable=>&$stopTablePaths)
			{
				foreach($stopTablePaths as &$stopTablePath)
				{
					if(!isset($paths[$startTable][static::IDX_RELATIONSHIPS]))
					{
						$paths[$startTable][static::IDX_RELATIONSHIPS]=array();
					}
					$idx=count($paths[$startTable][static::IDX_RELATIONSHIPS]);

					$paths[$startTable][static::IDX_RELATIONSHIPS][$idx]=$stopTablePath;
					//for each relationship find its type (one-2-one, one-2-many etc)
					//to know what form of its name will be used (plural or singular)
					
					
					
					$alias='';
					$reversePath=array_reverse($stopTablePath);
					$aliases=array();
					
					foreach($reversePath as $fkName=>$isChild)
					{
						$fk=$this->fks[$fkName];
						$break=false;
						
						
						$aliasParts=$this->buildFKAlias($fkName, $isChild, $stopTable, false);//isset($this->redundantFKs[$fkName]) /*|| ($this->isFkOne2One($this->fks[$fkName]) && count($reversePath)>1)*/);
						
						if(!$aliases)
						{
							$aliases=$aliasParts;
						}
						else
						{
							foreach($aliasParts as $aliasPart)
							{
								foreach($aliases as $key=>$alias)
								{
									$aliases[$key]=$aliasPart.'_'.$alias;
								}
							}
						}

							//$stopTablePath['ALIAS']='axxa';
						foreach($aliases as $alias)
						{
							if(!isset($aliasesBuilt[$alias]))
							{
								$paths[$startTable][static::IDX_RELATIONSHIPS_ALIASES][$this->buildJavaStyleId($alias)]=$idx;

								$stopTablePath['ALIAS']=$alias;
								$aliasesBuilt[$alias]=1;

								$break=true;
								
							}
						}
						if($break)
						{
							break;
						}
					}
					
				}
			}
		}
		
		foreach($paths as $startTable=>&$startTablePaths)
		{
			ksort($startTablePaths[static::IDX_RELATIONSHIPS_ALIASES]);
		}
		//echo '<pre>';
		//print_r($tablePaths['articles']); die();
		return $paths;
	}
	protected function buildTableRelationshipPaths($skipTablesOne2One, $skipTables, $backReferencedTables)
	{
		/*
		$startTable='users';
		$stopTable='groups';
		print_r($this->getPathBetweenTables($startTable, $stopTable));
		return;
		*/
		foreach($this->tableFKs as $startTable=>$startTableConnections)
		{
			foreach($this->tableFKs as $stopTable=>$stopTableConnections)
			{
				$rels=$this->getPathBetweenTables($startTable, $stopTable);
				
				//search for relations ships/fks that links the same table
				//(like in parent <-> child relationship where both parents and children are stored in the same table)
				//if these kind of relationships are found (and because how the code is build now it 
				//adds them with only one direction) then add a new relationsship in the ohter direction
				if($startTable==$stopTable && $rels)
				{
					foreach($rels as $rel)
					{
						if(count($rel)>1)
						{
							continue;
						}
						$rels[]=array(key($rel) => current($rel)==1?0:1);
					}
				}
				
				$paths[$startTable][$stopTable]=$rels;
				if(!$paths[$startTable][$stopTable])
				{
					unset($paths[$startTable][$stopTable]);
				}
			}
			
		}
		//print_r($paths); die();
		$paths=$this->sortRelationshipPaths($paths);//shortest paths first
		$paths=$this->buildRelationshipPathAliases($paths);
		
		return $paths;
	}
	public function getPathBetweenTables($startTable, $stopTable, $crossedPath=array(), $addNonOne2OneAndMany2Many=true, $prevFKIsOne2One=false, $depth=0)//$tableConnections, $fks, $tableIDs)//, $skipTablesOne2One, $skipTables, $backReferencedTables, $crosedPath=array(), $maxDepth=-1, $visitedTables=array())
	{
		
		$skipOne2OneTables=array('wfis'=>1);
		
		$paths=array();
				
		foreach($this->tableFKs[$startTable] as $fkName=>$isChild)
		{
			if(isset($crossedPath[$fkName]))
			{
				continue;
			}
			
			//$isChild shows position/index of $startTable in $fk
			//the other table of the fk is in the other key
			//it also shows how the fk is visited, from parent table to child table (0)
			//or from child table to parent table (1)
			$fk=$this->fks[$fkName];
			$isOne2One=$this->isFkOne2One($fk);
			$isMany2Many=$this->isFkMany2Many($fk);
			
			if((!$addNonOne2OneAndMany2Many && !$isOne2One && !$isMany2Many ) 
					|| ($isMany2Many && $depth>1)
					|| (isset($skipOne2OneTables[$startTable]) && $isOne2One && $prevFKIsOne2One) )
			{
				continue;
			}
			$fkOtherTable=$fk[$isChild?0:1][static::IDX_FK_TABLE];
			
			$fkDepth=$isOne2One?0:1;
			if($fkOtherTable==$stopTable)
			{
				$paths[]=$crossedPath+array($fkName=>$isChild?0:1);
			}
			
			if($paths2=$this->getPathBetweenTables($fkOtherTable, $stopTable, $crossedPath+array($fkName=>$isChild?0:1), $isOne2One && $addNonOne2OneAndMany2Many, $isOne2One, $depth+$fkDepth))
			{
				foreach($paths2 as $path)
				{
					$paths[]=$path;
				}
			}
		}
		
		return $paths;
		
	}
	protected function getTableFKs()
	{
		foreach($this->fks as $fkName=>$fk)
		{
			$tableConnections[$fk[0][static::IDX_FK_TABLE]][$fkName]=0;
			$tableConnections[$fk[1][static::IDX_FK_TABLE]][$fkName]=1;
		}
		return $tableConnections;
	}
	protected function getEscapedName()
	{
		return str_replace('.', '@002e', $this->name);
	}
	protected function loadFKs()
	{
		$dbName=$this->getEscapedName();
		$fks=array();
		$query='SELECT *
			FROM information_schema.INNODB_SYS_FOREIGN 
			WHERE information_schema.INNODB_SYS_FOREIGN.FOR_NAME LIKE \''.$dbName.'/%\'';
		
		if($fksRecordset=$this->loadArrayRecordset($query))
		{
			$query='SELECT *
					FROM information_schema.INNODB_SYS_FOREIGN_COLS
					WHERE information_schema.INNODB_SYS_FOREIGN_COLS.ID LIKE \''.$dbName.'/%\'';
			$fkColumnsRecordset=$this->loadArrayRecordset($query);
			foreach($fksRecordset as $fksRecord)
			{
				$ID=$fksRecord['ID'];
				$fkName=substr($ID, strpos($ID, '/')+1);
				
				$forTableName=substr($fksRecord['FOR_NAME'], strpos($fksRecord['FOR_NAME'], '/')+1);
				$refTableName=substr($fksRecord['REF_NAME'], strpos($fksRecord['REF_NAME'], '/')+1);
					
				$parentColumns=$childColumns=array();
				foreach($fkColumnsRecordset as $fkColumnsRecord)
				{
					if($fkColumnsRecord['ID']!=$ID)
					{
						continue;
					}
					$parentColumns[]=$fkColumnsRecord['REF_COL_NAME'];
					$childColumns[]=$fkColumnsRecord['FOR_COL_NAME'];
				}
				
				$fks[$fkName]=array(
					0 => array(static::IDX_FK_TABLE => $refTableName, static::IDX_FK_COLUMNS => $parentColumns),
					1 => array(static::IDX_FK_TABLE => $forTableName, static::IDX_FK_COLUMNS => $childColumns),
					2 => count($fks)+1,
					3 => $fkName
				);
			}
		}
		
		return $fks;
	}
	
	protected function loadTablesIdColumns()
	{
		$dbName=$this->getEscapedName();
		
		$query='SELECT SUBSTR(information_schema.INNODB_SYS_TABLES.NAME, LOCATE(\'/\', information_schema.INNODB_SYS_TABLES.NAME)+1) AS table_name, 
					GROUP_CONCAT(information_schema.INNODB_SYS_FIELDS.NAME) AS column_names,
					SUM(IF(LCASE(information_schema.COLUMNS.IS_NULLABLE)=\'YES\',1,0)) AS index_is_nullable,
					information_schema.INNODB_SYS_INDEXES.NAME AS index_name,
					information_schema.INNODB_SYS_INDEXES.TYPE,
					GROUP_CONCAT(IF(IFNULL(information_schema.COLUMNS.EXTRA, \'\')=\'auto_increment\', 1, 0)) AS extra
				FROM information_schema.INNODB_SYS_TABLES INNER JOIN  information_schema.INNODB_SYS_INDEXES
					USING(TABLE_ID)
				INNER JOIN information_schema.INNODB_SYS_FIELDS
					USING(INDEX_ID)
				INNER JOIN information_schema.COLUMNS
					ON information_schema.COLUMNS.TABLE_SCHEMA=\''.$this->name.'\'
						AND information_schema.COLUMNS.TABLE_NAME=SUBSTR(information_schema.INNODB_SYS_TABLES.NAME, LOCATE(\'/\', information_schema.INNODB_SYS_TABLES.NAME)+1)
						AND information_schema.COLUMNS.COLUMN_NAME=information_schema.INNODB_SYS_FIELDS.NAME
				WHERE information_schema.INNODB_SYS_TABLES.NAME LIKE \''.$dbName.'/%\'
					AND ( information_schema.INNODB_SYS_INDEXES.TYPE=3 OR information_schema.INNODB_SYS_INDEXES.TYPE=2)
					
				GROUP BY information_schema.INNODB_SYS_TABLES.NAME, index_name
				HAVING index_is_nullable=0';
			
			
		
			$pksRecordset=$this->loadArrayRecordset($query);
			//print_r($pksRecordset);die();
			foreach($pksRecordset as $pksRecord)
			{
				$idColumnNames=explode(',', $pksRecord['column_names']);
				$idColumnsExtra=explode(',', $pksRecord['extra']);
				$tableIDs[$pksRecord['table_name']][$pksRecord['index_name']]=array_combine($idColumnNames, $idColumnsExtra);
				
			}
			return $tableIDs;
	}
}