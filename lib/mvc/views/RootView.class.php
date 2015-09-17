<?php

include_once(__DIR__.'/View.class.php');

class RootView extends View
{
	const HEAD_TAG_PREPEND=1;
	const HEAD_TAG_REPLACE=2;
	const HEAD_TAG_APPEND=3;
	
	
	protected $meta=array();
	
	public function show($htmlTag='', $attributes='', $showInnerHtmlOnly=true)
	{
		parent::show( $htmlTag, $attributes, $showInnerHtmlOnly);
	}
	public function setMeta($metaPropertyValue, $content, $append, $metaPropertyName='name', $separator=' ')
	{
		if(!isset($this->meta[$metaPropertyName][$metaPropertyValue]))
		{
			$this->meta[$metaPropertyName][$metaPropertyValue]='';
		}
		switch($append)
		{
			case static::HEAD_TAG_PREPEND:
				$this->meta[$metaPropertyName][$metaPropertyValue]=$content.($this->meta[$metaPropertyName][$metaPropertyValue]?$separator:'').$this->meta[$metaPropertyName][$metaPropertyValue];
				break;
			case static::HEAD_TAG_REPLACE:
				break;
			case static::HEAD_TAG_APPEND:
				break;
		}
	}
}