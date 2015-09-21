<?php

namespace alib\model;

include_once(__DIR__.'/FormRelationshipPath.class.php');

class FormRelationshipRecordWrapper extends RelationshipRecordWrapper
{
	public $tableName;
	public function __construct($record, $relationshipPath=null, $rpk='', $tableName='')
	{
		if(is_object($record) && !is_a($record, 'FormRecord'))
		{
			//die('axxa');
			//root wrapper
			$record=new FormRecord($record->getArrayRecordId(), $record->getArrayRecord(), $tableName=$record->getTableName());
		}
		//$this->tableName=$tableName?$tableName:$record->getGenericTableName();
		parent::__construct($record, $relationshipPath, $rpk, $tableName);
	}
	
	protected function createRelationshipPath()
	{
		return new FormRelationshipPath($this, $this->record->getTableNamePrefix());
	}
	protected function getTableClassName($genericTableName)
	{
		return '\alib\model\FormRecord';
	}
	public function save($updateKeys=true)
	{
		//echo 'da:'.$this->record->getGenericTableName()."\n"; 
		//return;
		$tableName=$this->record->getGenericTableName();
		$className=parent::getTableClassName($tableName);
		
		foreach($idColumnNames=array_keys(RelationshipPath::$___relationships[Database::$___defaultInstance->getName()]['tables'][$tableName][Database::IDX_ID_COLUMNS]['PRIMARY']) as $idColumnName)
		{
			$arrRecordId[$idColumnName]=$this->record->$idColumnName;
		}
		
		$record=new $className($arrRecordId, null);
		
		foreach($this->record->getArrayRecord() as $columnName=>$columnValue)
		{
			try
			{
				$record->$columnName=$columnValue;
			}
			catch(\Exception $err)
			{
			}
		}
		try
		{
			$record->save();
			//echo get_class($this->record)."\n";
			$this->record->setArrayRecordId($record->getArrayRecordId());
			$this->record->setArrayRecord($record->getArrayRecord());
		}
		catch(\Exception $err)
		{
		}
	}
	
	public function export(&$source)
	{
		$this->relationshipPath->export($source);
		
	}
	public function import($source, $params, $decode=true)
	{
		$this->relationshipPath->import($source, $params, $decode);
	}
	
}