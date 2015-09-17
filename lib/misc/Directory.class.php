<?php

class Directory2
{
	protected $name;
	public function __construct($name)
	{
		$this->name=$name;
	}
	protected function getSubTree($dirName, $pattern, $flags=0, &$subtree=array())
	{
		$return=!$subtree;
		
		
		if($dirs=glob($dirName.'/*', GLOB_ONLYDIR))
		{
			foreach($dirs as $subDirName)
			{
				$this->getSubTree($subDirName, $pattern, $flags, $subtree);
			}
		}
		
		if($files=glob($dirName.'/'.$pattern, $flags))
		{
			foreach($files as $fileName)
			{
				$subtree[]=$fileName;
			}
		}
		
		
		if($return)
		{
			return $subtree;
		}
		
	}
	public function getTree($pattern, $flags=0)
	{
		return $this->getSubTree($this->name, $pattern, $flags);
	}
}

