<?php

namespace alib\model;

include_once(__DIR__.'/../../../../core/Exception.classes.php');
include_once(__DIR__.'/DatabaseSchema.class.php');

define('DEFAULT_DB_USERNAME', 'root');
define('DEFAULT_DB_PASSWORD', '');
define('DEFAULT_DB_HOST', 'localhost');

class Database extends \mysqli
{
	const IDX_RELATIONSHIPS=0;
	const IDX_RELATIONSHIPS_ALIASES=1;//'relationship_aliases';
	const IDX_ID_COLUMNS=2;//'id_columns';
	const IDX_ID_AUTOINCREMENT_COLUMN=3;//'auto_increment_column';
	
	const IDX_COLUMNS=4;//'columns';
	const IDX_COLUMN_ALIASES=5;//'column_aliases';
	
	
	const IDX_TYPE=6;//'type';
	const IDX_DEFAULT=7;//'default_value';
	const IDX_COMMENT=8;//'comment';
	const IDX_FLAGS=9;//'flags';
	const IDX_MIN_VALUE=11;//'minvalue';
	const IDX_MAX_VALUE=12;//'maxvalue';
	const IDX_MAX_LENGTH=13;//'max_length';
	const IDX_DIGITS=14;//'digits'; //number of digits before the dot in a decimal number
	const IDX_DECIMALS=15;//'decimals'; //number of digits after the dot in a decimal number
	
	
	const IDX_OPTIONS=16;//'options';
	const IDX_FKS=17;//'fks';
	const IDX_IDS=18;//'ids';
	const IDX_VERSION_FKS=19;//'version_fks';
	const IDX_VERSION_COLUMNS=20;//'version_columns';
	
	const TYPE_BINARY=0;//'binary';
	const TYPE_BIT=1;//'bit';
	const TYPE_BLOB=2;//'blob';
	const TYPE_STRING=3;//'string';
	const TYPE_DATE=4;//'date';
	const TYPE_DATETIME=5;//'datetime';
	const TYPE_DECIMAL=6;//'decimal';
	const TYPE_FLOAT=7;//'float';	
	const TYPE_INTEGER=8;//'integer';
	const TYPE_TIME=9;//'time';
	const TYPE_TIMESTAMP='10;//timestamp';
	
	
	const FLAG_NOT_NULL=1;
	const FLAG_UNSIGNED=2;
	const FLAG_MULTIPLE_OPTIONS=4;
	
	const IDX_FK_COLUMNS=0;//'fk_columns';
	const IDX_FK_TABLE=1;//'fk_table';
	const IDX_FK_TABLE_PK=2;//'table_pk';
	const IDX_FK_ONE_2_ONE=3;//'one_2_one';
	const IDX_FK_DIRECTION=4;//'direction';
	
	const SQL_ID_QUOTE='`';
	
	static protected $___instances=array();
	static public $___defaultInstance;
	static protected $___relationships=array();
	static protected $___schemas=array();
	
	protected $charset='utf8';
	protected $name, $hostName;
	protected $userName, $password;
	static protected $numQueries=0;
	
	/*instantiation/creation*/
	protected function __construct($name, $hostName, $userName, $password, $port=3306, $socket=null)
	{
		$this->name=$name;
		$this->hostName=$hostName;
		
		parent::__construct($this->hostName, $userName, $password, $this->name, $port);
		
		parent::set_charset($this->charset);	
	
	}
	static public function ___new($name, $setAsDefault, $hostName=DEFAULT_DB_HOST, $userName=DEFAULT_DB_USERNAME, $password=DEFAULT_DB_PASSWORD, $port=3306, $socket=null)
	{
		if(!isset(static::$___instances[$hostName][$name]))
		{
			static::$___instances[$hostName][$name]=new Database($name, $hostName, $userName, $password, $port, $socket);
		}
		if($setAsDefault)
		{
			static::$___defaultInstance=static::$___instances[$hostName][$name];
		}
		return static::$___instances[$hostName][$name];
	}
	public function getName()
	{
		return $this->name;
	}
	/*Basic Sql*/
	public function query($query)
	{
		static::$numQueries++;
		if(isset($GLOBALS['dbg']) &&$GLOBALS['dbg'])
		{
			echo $query."\n-------------------------------------------------------\n\n";
		}
		$result=parent::query($query);
		
		if($result===false)
		{
			throw new \Exception(__METHOD__.' sql error:'.$this->error.'<br>Query: '.htmlentities($query), 101);
		}
		
		return $result;
	}
	public function loadArrayRecord($query)
	{
		if ($result=$this->query($query))
		{
			$record=$result->fetch_assoc();
			$result->free();
			return $record;
		}
	}
	
	public function loadArrayRecordset($query, $idName=null, $idName2=null)
	{
		$recordset=array();
		
		if($result=$this->query($query))
		{
			if ($result->num_rows>0)
			{
				if (!$idName)
				{
					$recordset=$result->fetch_all(MYSQLI_ASSOC);
				}
				else
				{
					for($i=0; $i<$result->num_rows; $i++)
					{
						$result->data_seek($i);
						$record=$result->fetch_assoc();
						if($idName && $idName2)
						{
							$recordset[$record[$idName]][$record[$idName2]]=$record;
							continue;
						}
						
						$recordset[$record[$idName]]=$record;
					}
				}
			}

			$result->free();
			
			return $recordset;
		}
	}
	public function getNumQueries()
	{
		return static::$numQueries;
	}
	static public function ___getNumQueries()
	{
		return static::$numQueries;
	}
	/*Schemas info*/
	public function getRecordSchema($tableName, $tableNamePrefix='')
	{
		$configCacheFilename=AUTO_GENERATED_CONFIG_FILES_DIR.'/database/schemas.inc.php';
		
		if(!isset(static::$___schemas[$this->name][$tableName]))
		{
			if(defined('DEBUG'))
			{
				$dbSchema=new DatabaseSchema($this->name, $this->hostName, 'root', '', 'wp_');
		
				static::$___schemas[$this->name][$tableName]=$dbSchema->getRecordSchema($tableName, $tableNamePrefix);
				
				file_put_contents($configCacheFilename, '<?php'."\n".'static::$___schemas='.var_export(static::$___schemas, true).';');
			}
			
			include($configCacheFilename);
		}
		
		
		return static::$___schemas[$this->name][$tableName];
	}
	
	
	public function getRelationships($skipTablesOne2One=array(), $skipTables=array(), $backReferencedTables=array(), $tablesPrefix='')
	{
		$configCacheFilename=AUTO_GENERATED_CONFIG_FILES_DIR.'/database/relationships.inc.php';
		
		if(!isset(static::$___relationships[$this->name]))
		{
			if(defined('DEBUG'))
			{
				$dbSchema=new DatabaseSchema($this->name, $this->hostName, 'root', '', 'wp_');
		
				static::$___relationships[$this->name]=$dbSchema->getRelationships();
				
				file_put_contents($configCacheFilename, '<?php'."\n".'static::$___relationships='.var_export(static::$___relationships, true).';');
			}
			
			include($configCacheFilename);
		}
		
		
		return static::$___relationships[$this->name];
	}
	public function getTableClassName($tableName)
	{
		
	}
}


