<?php

include_once(__DIR__.'/FlatFile.class.php');

class FlatFilesTree extends FlatFile
{
	protected $contentDirName;
	protected $contentIndexDir='', $snippetsDir='snippets'; /* $snippetsDir - special dir where you can store dirs containing symlinks. 
																* Each dir represent the content to show in a page box/area/piece. 
																* Eg. "Navigation" may contain symlinks to articles whose titles you want to display in the main navigation
															*/
	protected $instances;
	
	public function __construct($contentDirName)
	{
		$this->contentDirName=realpath($contentDirName);
		$this->snippetsDir=$this->contentDirName.'/'.$this->snippetsDir;
		$this->instances['']=&$this;
		
		
		
		if(!$this->contentIndexDir)
		{
			$this->contentIndexDir=$this->contentDirName.'/.index';
		}
		if(!is_dir($this->contentIndexDir))
		{
			mkdir($this->contentIndexDir, 0777, true);
		}
		
		parent::__construct($this->contentDirName, $this);

		if($this->instances)
		{
			foreach($this->instances as $relativeDirName=>$flatFile)
			{
				
				if(!is_link($flatFile->getDirName()))
				{
					if($flatFile==$this)
					{
						continue;
					}
					$this->createFlatFileIndexSymlinkName($flatFile);
				}
				else
				{
					if($relativeDirName=='snippets/Home/2.Our Style')
					{
						//die($flatFile->getDirName()."\n".''.$this->readLink($flatFile->getDirName()));
					}
					
					foreach($this->instances as $relativeDirName2=>$flatFile2)
					{
						
						if($flatFile2==$flatFile || is_link($flatFile2->getDirName()))
						{
							continue;
						}
						
						if($this->readLink($flatFile->getDirName())==$flatFile2->getDirName())
						{
							if($relativeDirName=='snippets/Home/2.Our Style')
							{
								//echo $relativeDirName."\n".$this->readLink($flatFile->getDirName())."\n".$flatFile2->getDirName()."\n-------------------------------------------------\n";
								//die();
							}
							$flatFile->setSymlink2($flatFile2);
							break;
						}
					}
				}
			}
		}
	}
	protected function readLink($link)
	{
		
		$path=readlink($link);
		
		if($path[0]=='.')
		{
			if($link=='/usr/local/httpd-2.2.26/htdocs/generic-app-draft/app/content/en/snippets/Navigation/1.Home')
			{
				//echo substr_count($path, '../');
				//echo $link."\n".$path;
				//die('');
			}
			$numOccurences=substr_count($path, '../')+1;
			$path=preg_replace('|(/[^\/]+){'.$numOccurences.'}$|', '', $link).'/'.str_replace('../', '', $path);
		}
		
		return realpath($path);
	}
	public function getContentDirName()
	{
		return $this->contentDirName;
	}
	public function getContentIndexDir()
	{
		return $this->contentIndexDir;
	}
	public function getSnippetsDir()
	{
		return $this->snippetsDir;
	}
	public function &addInstance($relativePath, $instance)
	{
		$this->instances[$relativePath]=$instance;
		
		return $this->instances[$relativePath];
	}
	public function &getInstance($relativePath)
	{
		return $this->instances[$relativePath];
	}
	protected function createFlatFileIndexSymlinkName($flatFile)
	{
		$flatFileDirName=$flatFile->getDirName();
		$hasContent=false;
		
		if($files=glob($flatFileDirName.'/*'))
		{
			foreach($files as $file)
			{
				if(is_file($file))
				{
					$hasContent=true;
					break;
				}
			}
			if(!$hasContent)
			{
				return;
			}
		}
		
		$flatFileSymlink=$this->contentIndexDir.'/'.$flatFile->getBaseName();
		
		if(!is_link($flatFileSymlink))
		{
			symlink($flatFileDirName, $flatFileSymlink);
			return;
		}
		if(readlink($flatFileSymlink)==$flatFileDirName || is_link($flatFileSymlink=$flatFileSymlink.' ( '.str_replace('/', ' > ', $flatFile->getRelativePath()).' )'))
		{
			return;
		}
		
		symlink($flatFileDirName, $flatFileSymlink);
	}
	
	/* get articles */
	
	public function getArticle($propertyName, $propertyValue)
	{
		if(!$this->instances)
		{
			return;
		}
		
		if($propertyName=='uri')
		{
			$propertyValue=$this->formatCleanPath($propertyValue, true, true);
		}
		
		$propertyValue=strtolower($this->trimAll($propertyValue));
		
		foreach($this->instances as $contentDir)
		{
			if(strtolower($this->trimAll($contentDir->getProperty($propertyName)))==$propertyValue)
			{
				return $contentDir;
			}
		}

	}
	public function getArticleImageFullPathByUri($imageUri)
	{
		$pathInfo=pathinfo($imageUri);
		$article=$this->getArticleByUri($pathInfo['filename']);
		
		return $article->getDirName().'/image.'.$pathInfo['extension'];
	}
	protected function trimAll($str)
	{
		return trim(preg_replace('/[\s]+/', ' ', $str));
	}
}

// *100#
