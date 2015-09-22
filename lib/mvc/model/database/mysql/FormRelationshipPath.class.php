<?php

namespace alib\model;

include_once(__DIR__.'/RelationshipPath.class.php');

class FormRelationshipPath extends RelationshipPath
{
	const FORM_PATH_SEPARATOR='/';
	public function buildFormPrefix($rpk)
	{
		$prefix='';
		for($i=0; $i<=$this->pathLastIndex; $i++)
		{
			if(!($relationshipType=$this->path[$i][static::IDX_NODE_KEY]))
			{
				continue;
			}
			$prefix.=($prefix?static::FORM_PATH_SEPARATOR:'').$relationshipType;
		}
		return 'data['.$this->encodePrefix($prefix.static::FORM_PATH_SEPARATOR.$rpk).']';
	}
	protected function encodePrefix($prefix)
	{
		return $prefix;
		return base64_encode($prefix);
	}
	protected function decodePrefix($prefix)
	{
		return $prefix;
		return base64_decode($prefix);
	}
	protected function decodeSource($source)
	{
	}
	public function import($source, $params, $decode=true, $overwriteRoot=true)
	{
		if($decode)
		{
			$decodedSource=array();
			foreach($source as $encodedPath=>$arrRecord)
			{
				$decodedPath=$this->decodePrefix($encodedPath);

				$pathKeys=explode(static::FORM_PATH_SEPARATOR, $decodedPath);
				$numPathKeys=count($pathKeys);
			
				$ref=&$decodedSource;
				
				if($numPathKeys==2)
				{
					$decodedSource[static::IDX_DATA][$pathKeys[1]]=$arrRecord;
					
					continue;
				}
				else
				for($i=0; $i<$numPathKeys; $i++)
				{
					if($i<$numPathKeys-1)
					{
						if(!isset($ref[static::IDX_CHILDREN]))
						{
							$ref[static::IDX_CHILDREN]=array();
						}
						$ref=&$ref[static::IDX_CHILDREN];
					}
					else
					{
						if(!isset($ref[static::IDX_DATA]))
						{
							$ref[static::IDX_DATA]=array();
						}
						$ref=&$ref[static::IDX_DATA];
					}
					if(!isset($ref[$pathKeys[$i]]))
					{
						$ref[$pathKeys[$i]]=array();
					}
					$ref=&$ref[$pathKeys[$i]];
				}
				$ref=$arrRecord;
				//print_r($pathKeys);

			}
		}
		else
		{
			$decodedSource=$source;
		}
		//print_r($decodedSource);
		parent::import($decodedSource, $params, $overwriteRoot);
	}
}