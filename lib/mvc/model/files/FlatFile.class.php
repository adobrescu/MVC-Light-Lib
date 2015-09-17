<?php

class FlatFile
{
	protected $root;
	protected $children;
	protected $contentDirName;
	protected $dirName;
	protected $title, $uri, $index=0;
	protected $symlink2;
	
	public function __construct($dirName, $rootFlatFile)//$contentDirName)
	{
		
		$this->dirName=$dirName;
		$this->root=$rootFlatFile;
		$this->contentDirName=realpath($this->root->getContentDirName());
		
		$title=html_entity_decode(basename($this->getRelativePath()));
		if(preg_match('/^[\\s]*([0-9]+)[\\s]*\.(.+)$/', $title, $matches))
		{
			$this->index=$matches[1];
			$title=$matches[2];
		}
		$this->title=$title;
		$this->uri=$this->formatUri();
		
		//echo $dirName."\n".$this->getRelativePath()."\n-------------\n";
		
		if(!is_link($dirName))
		{
			$this->scanDir();
		}
		
		if($this->children)
		{
			uasort($this->children, array($this, 'compChildrenByIndex'));
		}
	}
	public function compChildrenByIndex($child1, $child2)
	{
		return $child1->index > $child2->index;
	}
	protected function scanDir()//$dirName, &$instances=null)
	{
		$dirNames=glob($this->dirName.'/.*', GLOB_ONLYDIR);
		
		if(count($dirNames=array_merge($dirNames, glob($this->dirName.'/*', GLOB_ONLYDIR)))==0)
		{
			return;
		}

		unset($dirNames[0], $dirNames[1]);

		foreach($dirNames as $dirName)
		{

			if(($dirName==$this->root->getContentIndexDir()) || basename($dirName)=='.' || basename($dirName)=='..')
			{
				continue;
			}

			$childRelativePath=$this->getRelativePath($dirName);
			
			$this->children[$childRelativePath]=$this->root->addInstance($childRelativePath, new FlatFile($dirName, $this->root) );
		}
		
	}
	public function setSymlink2($symlink2)
	{
		$this->symlink2=$symlink2;
	}
	public function getChildren()
	{
		return $this->children;
	}
	public function formatUri()
	{
		$len=strlen(strpos($this->dirName, $this->root->getSnippetsDir())===0 ? $this->root->getSnippetsDir() : $this->contentDirName);
			
		return $this->formatCleanPath(strtolower(
				preg_replace(
						array('/[^a-z0-9\_\-\/]+/i',
								'/\-$|^\-/'), 
						array('-',
								''), 
						substr($this->dirName, $len+1)
					)
			), true, true);
	}
	public function formatCleanPath($path, $leadingSlash=true, $trailingSlash=false)
	{
		return preg_replace(array('/[\/]+/', '/\/$/', '/^[\/]/' ), array('/', $trailingSlash?'/':'', $leadingSlash?'/':''), ($leadingSlash?'/':'').$path.($trailingSlash?'/':''));
	}
	public function getDirName()
	{
		return $this->dirName;
	}
	public function getBaseName()
	{
		return basename($this->dirName);
	}
	public function getRelativePath($path='')
	{	
		return substr($path ? $path : $this->dirName, strlen($this->contentDirName)+1);
	}
	public function hasProperty($propertyName)
	{
		static $properties=null;
		
		if(!$properties)
		{
			$properties=get_class_vars(get_called_class());
		}
		if(array_key_exists($propertyName, $properties))
		{
			return true;
		}
		return is_file($propertyFileName=$this->dirName.'/'.$propertyName.'.html');
	}
	public function getProperty($propertyName, $params=null)
	{
		static $properties=null;
		
		if(!$properties)
		{
			$properties=get_class_vars(get_called_class());
		}
		if(array_key_exists($propertyName, $properties))
		{
			
			if(strtolower($propertyName)=='uri' && $this->symlink2)
			{
				return $this->symlink2->getProperty('uri', $params);
			}
			return $this->$propertyName;
		}
		
		if(is_file($propertyFileName=$this->dirName.'/'.$propertyName.'.html'))
		{
			if($params)
			{
				foreach( $params as $paramName=>$paramValue)
				{
					${$paramName}=$paramValue;
				}
			}
			ob_start();
			include($propertyFileName);
			$propertyValue=ob_get_contents();
			ob_end_clean();
			return $propertyValue;
			//return file_get_contents($propertyFileName);
		}
		
	}	
	public function getAnchorName()
	{
		return str_replace('/', '_', $this->getProperty('uri'));
	}
	public function getImageFileName()
	{
		return $this->formatUri().'.gif';
	}
	public function getIconSrc()
	{
		if(!($icons=glob($this->dirName.'/icon-*')))
		{
			return;
		}
		
		return substr(basename($icons[0]), 5);
	}
}

