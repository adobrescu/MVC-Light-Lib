<?php

namespace alib\model;

include_once(__DIR__.'/../../../../core/Exception.classes.php');
include_once(__DIR__.'/FormRelationshipPath.class.php');

class FormRelationshipRecordWrapper extends RelationshipRecordWrapper
{
	use RecordSchema
	{
		RecordSchema::__construct as RecordSchema___construct;
	}
	protected $tableNamePrefix='';
	public $tableName;
	public function __construct($record, $relationshipPath=null, $rpk='', $tableName='')
	{
		
		if(is_object($record) && !is_a($record, 'FormRecord'))
		{
			//die('axxa');
			//root wrapper
			$record=new FormRecord($record->getArrayRecord(), $tableName=$record->getTableName());
		}
		$this->tableName=$tableName?$tableName:$record->getGenericTableName();
		parent::__construct($record, $relationshipPath, $rpk, $tableName);
		
		$this->RecordSchema___construct();
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
		//if($tableName=='content_translations')
		//{
			
		//}
		$className=parent::getTableClassName($tableName);
		//if($className=='ContentTranslation')
		{
			//echo 'Aici';
			//print_r($this->getArrayRecordId());
			//echo $className."\n";
		}
		//$idColumnNames=RelationshipPath::$___relationships[Database::$___defaultInstance->getName()]['tables'][$tableName][Database::IDX_ID_COLUMNS]['PRIMARY'];
		//print_r($idColumnNames);
		//print_r($this->record->getArrayRecord());
		$arrRecordId=array();
		foreach(array_keys($this->pkColumns) as $idColumnName)
		{
			if($this->record->$idColumnName)
			{
				$arrRecordId[$idColumnName]=$this->record->$idColumnName;
			}
		}
		
		//if(isset($arrRecordId))
		//{
			//echo 'id:';
		//	print_r($arrRecordId);
		//}
		
		
		$record=new $className($this->getArrayRecordId(), null);
		
		$errors=new \alib\Exception2();
		//echo '<pre>';
		foreach($this->record->getArrayRecord() as $columnName=>$columnValue)
		{
			//echo $columnName.'=>'.$columnValue."\n";
			if(isset($this->pkColumns[$this->columnAliases[$columnName]]) && !$columnValue)//do not set id fields
			{
				continue;
			}
			try
			{
				
				$record->$columnName=$columnValue;
			}
			catch(\Exception $err)
			{
				// @todo: collect errors
				//echo 'Exception: '.$columnName."\n";
				$errors->addException($err);
			}
		}
		
		$errors->throwIfErrors();
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
			echo "Save: \n".$err->getMessage();
			$errors->addException($err);
		}
		if($this->record->getGenericTableName()=='content_items')
		{
				//die('123');
		}
		// @todo: throw errors if any
		$errors->throwIfErrors();
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
		
		$this->relationshipPath->import($source, $params, $decode, true);
		//$this->relationshipPath->debugPrintData(); die();
		//print_r($source); die();
		//print_r(array_keys($this->relationshipPath->path[0][RelationshipPath::IDX_DATA]));
	}
	public function buildPathString($rpk)
	{
		return $this->relationshipPath->buildPathString($this->rpk);
	}
	public function getMostRightTableName()
	{
		return $this->relationshipPath->getMostRightTableName();
	}
	public function buildHtmlInput($name, $type, $value=null)
	{
		if($type=='checkbox')
		{
			$html=$this->buildHtmlInput($name, 'hidden', $this->columns[$name][Database::IDX_DEFAULT]);
			$attrs=($this->$name=='1'?' checked':'');
			
			$value='1';
		}
		else
		{
			$attrs='';
		}
		$html=(isset($html)?$html:'').'<input type="'.$type.'" name="'.$this->relationshipPath->buildPathString($this->rpk).'['.$name.']" value="'.(is_null($value)?$this->$name:$value).'" '.$attrs;
		
		if($type!='hidden')
		{
			$html.=' id="'.$this->relationshipPath->getMostRightTableName().'_'.$name.'"';
		}
		
		$html.='>';
		
		return $html;
	}
	public function buildHtmlTextArea($name, $value=null)
	{
		
		$html='<textarea name="'.$this->relationshipPath->buildPathString($this->rpk).'['.$name.']" id="'.$this->relationshipPath->getMostRightTableName().'_'.$name.'">'.(is_null($value)?$this->$name:$value).'</textarea>';
		
		
		
		return $html;
	}
	public function buildHtmlPkInput()
	{
		$html='';

		foreach(array_keys($this->pkColumns) as $idColumnName)
		{
			$html.=($html?"\n":'').$this->buildHtmlInput($idColumnName, 'hidden');
		}
		return $html;
	}
	public function buildHtmlDbSelect($name, $value, $relationshipAliases, $titleColumnName)
	{
		//echo $name.', '.$relationshipAliases;
		if(!is_array($relationshipAliases))
		{
			$relationshipAliases=array($relationshipAliases);
		}
		//$fk=$this->fks[key($this->relationships[$this->relationshipAliases[current($relationshipAliases)]])];
		foreach($this->fks as $fk)
		{
			if($fk[1][Database::IDX_FK_TABLE]!=$this->tableName)
			{
				continue;
			}
			foreach($fk[1][Database::IDX_FK_COLUMNS] as $fkColumnName)
			{
				if($fkColumnName==$name)
				{
					break 2;
				}
			}
		}
		//print_r($fk[0][Database::IDX_FK_TABLE]);
		$items=new \alib\model\Recordset($fk[0][Database::IDX_FK_TABLE]);
		
		$html='<select name="'.$this->relationshipPath->buildPathString($this->rpk).'['.$name.']" id="'.$this->relationshipPath->getMostRightTableName().'_'.$name.'">';
		
		for($items->moveFirst();
			!$items->isEOF();
			$items->moveNext())
		{
			for($items->currentRecord->{current($relationshipAliases)}->where('lang=\'en\'')->moveFirst();
				!$items->currentRecord->{current($relationshipAliases)}->where('lang=\'en\'')->isEOF();
				$items->currentRecord->{current($relationshipAliases)}->where('lang=\'en\'')->moveNext())
			{
				$selected=$items->{current($fk[0][Database::IDX_FK_COLUMNS])}==$value?' selected':'';
				$html.='<option value="'.$items->{current($fk[0][Database::IDX_FK_COLUMNS])}.'"'.$selected.'>'.$items->currentRecord->{current($relationshipAliases)}->where('lang=\'en\'')->$titleColumnName.'</option>';
			}
		}
			
		
		$html.='</select>';
		
		return $html;
	}
	public function buildHtmlFormField($name, $label, $type)
	{
		$template='<div class="form-field clearfix">
			<label for="%label_for">%label</label>
			%input
			</div>';
		$args=func_get_args();
		//echo $name.'='.$this->$name."\n";
		switch($type)
		{
			case 'textarea':
				$htmlInput=$this->buildHtmlTextArea($name, $this->$name);
				break;
			case 'db_select':
				$htmlInput=$this->buildHtmlDbSelect($name, $this->$name, $args[3], $args[4]);
				break;
			default:
				$htmlInput=$this->buildHtmlInput($name, $type, $this->$name);
		}
		$htmlInput.="\n";
		$html=str_replace(array('%label_for', '%label', '%input' ),
				array(
						$this->relationshipPath->getMostRightTableName().'_'.$name,
						$label,
						$htmlInput
					),
				$template
				);
		return $html."\n";
	}
}