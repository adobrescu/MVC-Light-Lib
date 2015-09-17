<?php

class FlatContentArticlesMenu extends View
{
	protected $flatFile;
	
	public function __construct($gId, $gFlags, $flatFile, $templateFileName='')
	{
		parent::__construct($gId, $gFlags, $flatFile, $templateFileName?$templateFileName:__DIR__.'/articles-menu.html.php');
		
		$this->flatFile=$flatFile;
	}
}