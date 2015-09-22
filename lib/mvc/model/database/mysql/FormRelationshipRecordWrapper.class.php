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
			if($this->record->$idColumnName)
			$arrRecordId[$idColumnName]=$this->record->$idColumnName;
		}
		
		$record=new $className(isset($arrRecordId)?$arrRecordId:null, null);
		//print_r($arrRecordId);
		foreach($this->record->getArrayRecord() as $columnName=>$columnValue)
		{
			try
			{
				//echo $columnName.'=>'.$columnValue."\n";
				$record->$columnName=$columnValue;
			}
			catch(\Exception $err)
			{
				// @todo: collect errors
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
			// @todo: collect errors
		}
		
		// @todo: throw errors if any
	}
	
	public function export(&$source)
	{
		$this->relationshipPath->export($source);
		
	}
	public function import($source, $params, $decode=true)
	{
		//$this->relationshipPath->path[0][RelationshipPath::IDX_DATA]=array();
		//$key=key($this->relationshipPath->path[0][RelationshipPath::IDX_DATA]);
		//unset($this->relationshipPath->path[0][RelationshipPath::IDX_DATA][$key]);
		//echo $key;
		//$this->relationshipPath->debugPrintData(); die();
		$this->relationshipPath->import($source, $params, $decode, true);
		
		//print_r(array_keys($this->relationshipPath->path[0][RelationshipPath::IDX_DATA]));
	}
	
}