<?php

namespace alib\model;

include_once(__DIR__.'/Database.class.php');

const ERR_WRONG_FORMAT=100;
	const ERR_REQUIRED=101;
	const ERR_OUT_OF_RANGE=102;
	const ERR_TO_LONG=103;
	const ERR_INVALID_VALUE=104;

trait RecordSchema
{
	protected $database;
	protected $schema;
	protected $columns, $columnAliases; //columns info and (type, length etc) and aliases (eg id_user, idUser)
	protected $pkColumns; //columns that compose the PK
	protected $fks, $relationships, $relationshipAliases;
	protected $autoIncrementColumnName; //auto_increment column (part of PK)
	static $___typeFormatPatterns=array(
		Database::TYPE_BINARY => '',
		Database::TYPE_BIT => '[01]+',
		Database::TYPE_BLOB => '',
		Database::TYPE_STRING => '',
		Database::TYPE_DATE => '([0-9]{4})-([0-9]{2})-([0-9]{2})',
		Database::TYPE_DATETIME => '([0-9]{4})-([0-9]{2})-([0-9]{2})([ ]+([0-9]{2}):([0-9]{2}):([0-9]{2})){0,1}',
		Database::TYPE_DECIMAL => '[+\-]{0,1}[0-9]{0,3}([,]{0,1}[0-9]{3})*([\.]{1}[0-9]+){0,1}',
		Database::TYPE_FLOAT => '',
		Database::TYPE_INTEGER => '[+\-]{0,1}[0-9]+',
		Database::TYPE_TIME => '[0-9]{2}\:[0-9]{2}\:[0-9]{2}){0,1}',
		Database::TYPE_TIMESTAMP => '([0-9]{4})-([0-9]{2})-([0-9]{2})([ ]+([0-9]{2}):([0-9]{2}):([0-9]{2})){0,1}',
	);
	public function __construct()
	{
		$this->database=Database::$___defaultInstance;
		
		/*get table info*/
		$this->schema=$this->database->getRecordSchema($this->tableName, $this->tableNamePrefix);
		$this->fks=$this->schema['fks'];
		$this->relationshipAliases=$this->schema[Database::IDX_RELATIONSHIPS_ALIASES];
		$this->relationships=$this->schema[Database::IDX_RELATIONSHIPS];
		//print_r($this->schema); die();
		$this->columns=$this->schema[Database::IDX_COLUMNS];
		$this->columnAliases=$this->schema[Database::IDX_COLUMN_ALIASES];
		$this->pkColumns=$this->schema[Database::IDX_ID_COLUMNS]['PRIMARY'];
		$this->autoIncrementColumnName=$this->schema[Database::IDX_ID_AUTOINCREMENT_COLUMN];
	}
	public function validateColumn($columnName, $columnValue)
	{
		$columnDef=$this->columns[$columnName];
		
		//check if required and empty
		if( (($isEmpty=!preg_match("/[^ \r\n\t]+/", $columnValue)) && 
				$columnDef[Database::IDX_FLAGS] & Database::FLAG_NOT_NULL)
				)
		{
			throw new \Exception('', ERR_REQUIRED);
		}
		
		if($isEmpty)
		{
			return;
		}
		
		//echo $columnName."\n";
		//	print_r($columnDef);
		//	echo static::$___typeFormatPatterns[$columnDef[Database::IDX_TYPE]]."\n---------------------------------------------------\n";
		
		//check format
		
		if(static::$___typeFormatPatterns[$columnDef[Database::IDX_TYPE]])
		{
			if(!preg_match('/^'.static::$___typeFormatPatterns[$columnDef[Database::IDX_TYPE]].'$/', $columnValue, $columnValueParts))
			{
				throw new \Exception('', ERR_WRONG_FORMAT);
			}
			
		}
		
		//check for valid value
		$invalidValue=false;
		switch($columnDef[Database::IDX_TYPE])
		{
			case Database::TYPE_STRING:
				if(isset($columnDef[Database::IDX_OPTIONS]))
				{
					if(!($columnDef[Database::IDX_FLAGS] & Database::FLAG_MULTIPLE_OPTIONS))
					{
						if(!isset($columnDef[Database::IDX_OPTIONS][$columnValue]))
						{
							$invalidValue=true;
						}
					}
					else
					{
						foreach($options=explode(',', $columnValue) as $option)
						{
							if(!isset($columnDef[Database::IDX_OPTIONS][$option]))
							{
								$invalidValue=true;
							}
						}
					}
				}
				break;
			case Database::TYPE_DATE:
				$invalidValue=(!checkdate($columnValueParts[2], $columnValueParts[3], $columnValueParts[1]));
				break;
			case Database::TYPE_TIME:
				$invalidValue=((isset($columnValueParts[5]) && $columnValueParts[5]>23) || (isset($columnValueParts[6]) && $columnValueParts[6]>59) || (isset($columnValueParts[7]) && $columnValueParts[7]>59));
				break;
			case Database::TYPE_TIMESTAMP:
			case Database::TYPE_DATETIME:
				$invalidValue=(!checkdate($columnValueParts[2], $columnValueParts[3], $columnValueParts[1])
					|| 	
					((isset($columnValueParts[5]) && $columnValueParts[5]>23) || (isset($columnValueParts[6]) && $columnValueParts[6]>59) || (isset($columnValueParts[7]) && $columnValueParts[7]>59)) );
				break;
			
		}
		
		if($invalidValue)
		{
			throw new \Exception('', ERR_INVALID_VALUE);
		}
		//check length
		//@todo de verificat daca este adevarat: lungimea campului in MySql reprezinta numarul de bytes pe care acesta il ocupa
		//si nu numarul de caractere. De exemplu, "ĂÎ" ocupa 4 bytes si ar avea loc intr-un CHAR(2), in timp ce "ĂÎȘ" nu ar avea loc
		//La fel, strlen intoarce numarul de bytes pe care il ocupa un string, si nu numarul de caractere.
		//Deci este ok comparatia intre cele 2 (lungime camp si lungime string/strlen) pentru validare
		if(isset($columnDef[Database::IDX_MAX_LENGTH]) && $columnDef[Database::IDX_MAX_LENGTH]<strlen($columnValue))
		{
			throw new \Exception('', ERR_TO_LONG);
		}
		//check min/max values
		if((isset($columnDef[Database::IDX_MIN_VALUE]) && $columnValue<$columnDef[Database::IDX_MIN_VALUE])
			||
			(isset($columnDef[Database::IDX_MAX_VALUE]) && $columnValue>$columnDef[Database::IDX_MAX_VALUE])
			||
			(($columnDef[Database::IDX_FLAGS] & Database::FLAG_UNSIGNED) && $columnValue<0)
			||
			(
					isset($columnDef[Database::IDX_DIGITS]) && ($digitsAndDecimals=explode('.', str_replace(',', '', $columnValue))) &&
					!( strlen($digitsAndDecimals[0])<= $columnDef[Database::IDX_DIGITS] && (!isset($digitsAndDecimals[1])?true:strlen($digitsAndDecimals[1])<=$columnDef[Database::IDX_DECIMALS]))
					
			)
			)
		{
			throw new \Exception('', ERR_OUT_OF_RANGE);
		}
	}
	/*simple getters*/
	public function getTableName()
	{
		return $this->tableNamePrefix.$this->tableName;
	}
	public function getGenericTableName()
	{
		return $this->tableName;
	}
	public function getTableNamePrefix()
	{
		return $this->tableNamePrefix;
	}
	public function getDatabase()
	{
		return $this->database;
	}
	/* build sql statements methods*/
	public function buildSqlInsert($arrRecord)
	{
		$queryColumnNames=$queryColumnValues='';
		
		foreach($arrRecord as $columnName=>$columnValue)
		{
			$queryColumnNames.=($queryColumnNames?',':'').Database::SQL_ID_QUOTE.$columnName.Database::SQL_ID_QUOTE;
			$queryColumnValues.=($queryColumnValues?',':'').$this->buildSqlColumnValue($columnName, $columnValue);
		}
		$query='INSERT INTO '.Database::SQL_ID_QUOTE.$this->tableNamePrefix.$this->tableName.Database::SQL_ID_QUOTE.'
				('.$queryColumnNames.')
				VALUES
				('.$queryColumnValues.')';
		return $query;
	}
	public function buildSqlSelect($arrRecordId)
	{
		$query='SELECT *
			FROM `'.$this->tableNamePrefix.$this->tableName.'`
			WHERE '.$this->buildSqlWhereId($arrRecordId);
		
		return $query;
	}
	public function buildSqlUpdate($arrRecordId, $arrRecord)
	{
		$query='';
		foreach($arrRecord as $columnName=>$columnValue)
		{
			if(isset($this->pkColumns[$columnName]) && $arrRecordId[$columnName]==$columnValue)
			{
				continue;
			}
			//echo $columnName."\n";
			//print_r($this->columns[$columnName]);
			//echo "\nXXXXXXXXXXXXXXXXXXXXX\n";
			//skip timestamps with a default of CURRENT_TIMESTAMP
			if($this->columns[$columnName][Database::IDX_TYPE]==Database::TYPE_TIMESTAMP && strtolower($this->columns[$columnName][Database::IDX_DEFAULT])=='current_timestamp')
			{
				continue;
			}
			
			$query.=($query?','."\n":'').Database::SQL_ID_QUOTE.$columnName.Database::SQL_ID_QUOTE.'='.$this->buildSqlColumnValue($columnName, $columnValue);
		}
		
		if(!$query)
		{
			return;
		}
		
		$query='UPDATE '.Database::SQL_ID_QUOTE.$this->tableNamePrefix.$this->tableName.Database::SQL_ID_QUOTE.'
			SET '.$query.' WHERE '.$this->buildSqlWhereId($arrRecordId);
		
		return $query;
	}
	public function buildSqlDelete($arrRecordId)
	{
		return 'DELETE FROM 
			'.Database::SQL_ID_QUOTE.$this->tableNamePrefix.$this->tableName.Database::SQL_ID_QUOTE.
			' WHERE '.$this->buildSqlWhereId($arrRecordId);
		
		
	}
	public function buildSqlWhereId($arrRecordId)
	{
		$query='';
		
		foreach($arrRecordId as $pkColumnName=>$pkColumnValue)
		{
			$query .= ($query? ' AND ':'').$this->tableName.'.'.Database::SQL_ID_QUOTE.$pkColumnName.Database::SQL_ID_QUOTE.'='.$this->buildSqlColumnValue($pkColumnName, $pkColumnValue);
		}
		
		return $query;
	}
	public function buildSqlColumnValue($columnName, $columnValue)
	{
		
		$columnSchema=$this->columns[$columnName];
		
		
		switch ($columnSchema[Database::IDX_TYPE])
		{
			case Database::TYPE_INTEGER:
			case Database::TYPE_DECIMAL:
			case Database::TYPE_FLOAT:
				return $columnValue==='' || is_null($columnValue)?'NULL':$columnValue;
				break;
			case Database::TYPE_BIT:
				return $columnValue==='' || is_null($columnValue)?'NULL':'b\''.$columnValue.'\'';
				break;
			case Database::TYPE_STRING:
			case Database::TYPE_DATETIME:
			case Database::TYPE_DATE:
			case Database::TYPE_TIME:
			case Database::TYPE_TIMESTAMP:
			case Database::TYPE_BLOB:
			case Database::TYPE_BINARY:
				return is_null($columnValue)?'NULL':'\''.addslashes($columnValue).'\'';//$this->real_escape_string($columnValue).'\'';
				break;
		}
	}
}

class ReadOnlyRecord 
{
	use RecordSchema
	{
		RecordSchema::__construct as RecordSchema___construct;
	}
	//protected $tableName;
	protected $arrRecord, $arrRecordId; //references to coresponding record arrays from $___arrRecords and $___arrRecordIds
	//protected $database;
	static protected $___arrRecords, $___arrRecordIds; //array records 
	
	protected $loaded=true;
	
	protected $newRecordPkIndex; //set for new records (no id) until they are saved
	
	protected $uqid;
	
	
		
	//@todo : explain $id/$arrRecord combinations
	/*
	 * $id set => persistent record (stored into db)
	 * $id: SELECT, DELETE
	 * $id, $arrRecord: UPDATE
	 * 
	 * $arrRecord only: sint 2 variante:
	 * 
	 * 1. din $arrRecord se poate incarca $arrRecordId si exista $___arrRecords corespunzator id-ului => persistent
	 * 2. din $arrRecord nu se poate incarca $arrRecordId sau nu exista $___arrRecords corespunzator id-ului => not persistent
	 * 
	 * Exista si varianta ca id sa se poate incarca din $arrRecord si sa nu existe  $___arrRecords corespunzator id-ului dar totusi inregistrarea
	 * sa existe in bd dar sa nu fi fost incarcata si atunci ar trebui ca record-ul sa fie persistent
	 * 
	 * @fixme poate ar trebui un select care sa lamureasca situatia de mai sus?
	 *  
	 */
	
	
	public function __construct($arrRecord)
	{
		// @todo ??
		/*
		if(!$id && !$arrRecord)
		{
			die(__METHOD__.': no id and no record array specified');
		}
		*/
		
		$this->RecordSchema___construct();
		//$this->RecordSchema___construct();
		//$id2=$id;
		//$id=array();
		
		$id=null;
		if(false)
		{
			if(!is_array($id)) //assume a PK with only one column
			{
				$id=array(key($this->pkColumns) => $id);
			}
			foreach($id as $k=>$v)
			{
				$arrRecord[$k]=$v;
			}
			$id=array();
		}
		
		if($arrRecord)
		{
			if(!is_array($arrRecord))
			{
				$arrRecord=array(key($this->pkColumns) => $arrRecord);
			}
			foreach($this->pkColumns as $pkColumnName=>$autoIncrement)
			{
				if(!isset($arrRecord[$pkColumnName]) || !$arrRecord[$pkColumnName] )//pk not fully specified
				{
					$id=array();
					break;
				}
				$id[$pkColumnName]=$arrRecord[$pkColumnName];
		
			}
			if($id)
			{
				foreach($id as $pkColumnName=>$pkColumnValue)
				{
					unset($arrRecord[$pkColumnName]);
				}
			}
		}
		
		$this->uqid=uniqid();
		//$this->dataKey=$dataKey;
		/*asta e pasat la constructia de relationship-uri*/
				
		if($id && !is_array($id)) //assume a PK with only one column
		{
			$id=array(key($this->pkColumns) => $id);
		}
		if($id)//din id-ul primit se pastreaza numai campurile care compun pk-ul
		{
			$id=$this->getIdFromArray($id);
		}
		else
		if(!$id && $arrRecord)
		{
			//daca nu s-a primit un id dar acesta poate fi incarcat din array-ul primit ca record,
			//trebuie verificat daca exista in bd o inregistrare cu acel id pentru a se stabili daca 
			//record-ul este persistent sau nu
			if($id=$this->getIdFromArray($arrRecord))
			{
				$query='';
				
				foreach($id as $pkColumnName=>$pkColumnValue)
				{
					$query.=($query?' AND ':'').'`'.$this->tableName.'`.`'.$pkColumnName.'`='.$this->buildSqlColumnValue($pkColumnName, $pkColumnValue);
				}
				
				$query='SELECT 1 FROM `'.$this->tableName.'` WHERE '.$query;
				
				if(!$this->database->loadArrayRecord($query))
				{
					$id=null;
				}
			}
		}
		//set $arrRecord, $arrRecordId to reference coresponding entries from $___arrRecords and $___arrRecordIds
		$this->setRecordReference($id, $arrRecord);
		
		
		
	}
	
	public function getUqId()
	{
		return $this->uqid;
	}
	protected function getIdFromArray($arr)
	{
		foreach($this->pkColumns as $pkColumnName=>$extra)
		{
			if(!isset($arr[$pkColumnName]))
			{
				return null;
			}
			$id[$pkColumnName]=$arr[$pkColumnName];
		}
		return $id;
	}
	
	
	public function &__get($propertyName)
	{
		if(isset($this->columnAliases[$propertyName]))
		{
			if(!$this->loaded)
			{
				$this->select();
			}
			return $this->arrRecord[$this->columnAliases[$propertyName]];
		}
		
		echo __METHOD__.': property not found: '.$propertyName;
		throw new \Exception();
	}
	
	
	
	public function getArrayRecord()
	{
		if(!$this->loaded)
		{
			$this->select();
		}
		return $this->arrRecord;
	}
	public function getArrayRecordId()
	{
		return $this->arrRecordId;
	}
	
	/*simple getters for debugging*/
	public function &debugGetArrayRecordRef()
	{
		if(!$this->loaded)
		{
			$this->select();
		}
		return $this->arrRecord;
	}
	public function &debugGetArrayRecordIdRef()
	{
		return $this->arrRecordId;
	}
	
	/*db operations*/
	
	/*********************************************************************/
	protected function setRecordReference($id, $arrRecord)
	{
		if($id)
		{
			$pkIndex=$this->buildPKIndex($id);
			
			if(isset(static::$___arrRecords[$this->tableName][$pkIndex]))
			{
				$this->arrRecord=&static::$___arrRecords[$this->tableName][$pkIndex];
				$this->arrRecordId=&static::$___arrRecordIds[$this->tableName][$pkIndex];
				$this->loaded=true;
				
				if($arrRecord)
				{
					static::$___arrRecords[$this->tableName][$pkIndex]=$arrRecord;
					$this->loadIdFromArray($id);
				}
				
				return;
			}
			
			
		}
		else
		{
			//$pkIndex=$this->newRecordPkIndex=uniqid();
			$pkIndex=$this->uqid;
		}
		
		static::$___arrRecords[$this->tableName][$pkIndex]=$arrRecord?$arrRecord:array();
		$this->arrRecord=&static::$___arrRecords[$this->tableName][$pkIndex];
		
		static::$___arrRecordIds[$this->tableName][$pkIndex]=$id?$id:array();
		$this->arrRecordId=&static::$___arrRecordIds[$this->tableName][$pkIndex];
		
		if($id)
		{
			$this->loadIdFromArray($id);
			$this->loaded=$arrRecord?true:false;
		}
	}
	protected function updateRecordReference()
	{
		if($this->isPersistent())
		{
			$pkIndex=$this->buildPKIndex($this->arrRecordId);
		}
		else
		{
			$pkIndex=$this->newRecordPkIndex;
		}
		$newPkIndex=$this->buildPKIndex($this->arrRecord);
		
		if($pkIndex==$newPkIndex)
		{
			return;
		}
		
		static::$___arrRecords[$this->tableName][$newPkIndex]=&$this->arrRecord;
		$this->arrRecord=&static::$___arrRecords[$this->tableName][$newPkIndex];
		unset(static::$___arrRecords[$this->tableName][$pkIndex]);
		
		$this->loadIdFromArray($this->arrRecord);
		
		static::$___arrRecordIds[$this->tableName][$newPkIndex]=&$this->arrRecordId;
		$this->arrRecordId=&static::$___arrRecordIds[$this->tableName][$newPkIndex];
		unset(static::$___arrRecordIds[$this->tableName][$pkIndex]);
	}
	/*********************************************************************/
	public function isPersistent()
	{
		return $this->arrRecordId ? true : false;
	}
	protected function loadIdFromArray($arr)
	{		
		foreach($this->pkColumns as $pkColumnName=>$extra)
		{
			$this->arrRecordId[$pkColumnName]=$this->arrRecord[$pkColumnName]=$arr[$pkColumnName];
			
		}
	}
	protected function buildPKIndex($id)
	{
		$pkIndex='';
		foreach($this->pkColumns as $pkColumnName=>$pkColumnExtra)
		{
			$pkIndex.=($pkIndex?'-':'').$id[$pkColumnName];
		}
		return $pkIndex;
	}
	
	
	protected function select()
	{
		$query=$this->buildSqlSelect($this->arrRecordId);
		$arrRecord=$this->database->loadArrayRecord($query);
		foreach($arrRecord as $columnName=>$columnValue)
		{
			$this->arrRecord[$columnName]=$columnValue;
		}
	}
}



class Record extends ReadOnlyRecord
{
	

	
	public function save()
	{
		if($this->isPersistent())
		{
			$this->update();
		}
		else
		{
			$this->insert();
		}
		
		$this->updateRecordReference();
	}
	public function __set($propertyName, $propertyValue)
	{
		//echo  'aici: '.$propertyName.'('.$this->columnAliases[$propertyName].')='.$propertyValue."\n";
		//if($propertyName=='seo_options')
		//{
			//print_r($this->arrRecordId); die('123');
		//}
		if(isset($this->columnAliases[$propertyName]))
		{
			$this->validateColumn($this->columnAliases[$propertyName], $propertyValue);
			$this->arrRecord[$this->columnAliases[$propertyName]]=$propertyValue;
		}
		else
		{
			/*
			echo(__METHOD__.': column '.$this->tableName.'.'.$propertyName.' not found');
			//print_r(debug_backtrace(10));
			die();
			 */
		}
	}
	protected function update()
	{
		if($query=$this->buildSqlUpdate($this->arrRecordId, $this->arrRecord))
		{
			$this->database->query($query);
		}
	}
	protected function insert()
	{
		$query=$this->buildSqlInsert($this->arrRecord);
		$this->database->query($query);
		if($this->autoIncrementColumnName)
		{
			$this->arrRecord[$this->autoIncrementColumnName]=$this->database->insert_id;
		}
	}
	
	public function delete()
	{
		$this->database->query($this->buildSqlDelete($this->arrRecordId));
		
		$pkIndex=$this->buildPKIndex($this->arrRecordId);
		
		static::$___arrRecords[$this->tableName][$pkIndex]=null;
		unset(static::$___arrRecords[$this->tableName][$pkIndex]);
		
		static::$___arrRecordIds[$this->tableName][$pkIndex]=null;
		unset(static::$___arrRecordIds[$this->tableName][$pkIndex]);
	}
	
}

class FormRecord extends ReadOnlyRecord
{
	
	protected $tableName='', $tableNamePrefix='';
	
	public function __construct($arrRecord, $tableName)
	{
		$this->tableName=$tableName;
		parent::__construct($arrRecord);
	}
	public function __set($propertyName, $propertyValue)
	{
		if(isset($this->columnAliases[$propertyName]))
		{
			$this->arrRecord[$this->columnAliases[$propertyName]]=$propertyValue;
		}
	}
	public function setArrayRecord($arrRecord)
	{
		$this->arrRecord=$arrRecord;
	}
	
	public function setArrayRecordId($arrRecordId)
	{
		$this->arrRecordId=$arrRecordId;
	}
}

