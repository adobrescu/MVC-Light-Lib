<?php

include_once(__DIR__.'/RootController.class.php');
include_once(ALIB_DIR.'/mvc/model/FlatFilesTree.class.php');

class FlatFilesContentController extends RootController
{	
	
	protected $defaultIndexFileName='index.html';
	protected $flatFilesTree, $currentFlatFile;
	
	public function __construct($gId, $gFlags, $rootView, $documentRootUrl, $contentDir='')
	{
		ob_start();
		
		parent::__construct($gId, $gFlags, $rootView, $documentRootUrl);
		
		$this->flatFilesTree=new FlatFilesTree(CONTENT_FLAT_FILES_DIR);
	}
	public function __destruct()
	{
		parent::__destruct();
		
		$this->createStaticPage();
	}
	public function getCurrentFlatFile()
	{
		return $this->currentFlatFile;
	}
	protected function createStaticPage()
	{
		if(is_null($this->staticPageUri))
		{
			return;
		}
		
		if(!is_dir($dirName=STATIC_WEBROOT_PUBLIC.'/'.$this->getRelativeUri($this->staticPageUri)))
		{
			mkdir($dirName, 0777, true);
		}
		file_put_contents($fileName=$dirName.'/'.$this->defaultIndexFileName, ob_get_contents());
		ob_end_clean();
		echo file_get_contents($fileName);

	}
}