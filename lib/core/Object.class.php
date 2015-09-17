<?php

include_once(__DIR__.'/Exception.class.php');

class Object
{
	static public $___cacheConfigDir;
			
	protected static $___instances;
	
	protected static $___relativeRedirectUrl;
	
	protected $gId, $gFlags;
		
	protected $initialisationScriptFileName;
	protected $rootController;
	public function __construct($gId, $gFlags)
	{
		if(isset(static::$___instances[$gId]))
		{
			die('An Object instance with the same gId <b>'.$gId.'</b> already exists');
		}
		$this->gId=$gId;
		$this->gFlags=$gFlags;
		
		$debugBacktrace=debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
		
		for($i=0; $i<count($debugBacktrace); $i++)
		{
			if(strtolower($debugBacktrace[$i]['function'])!='__construct')
			{
				break;
			}
			
			$prevFileName=$debugBacktrace[$i]['file'];
		}
		
		$this->initialisationScriptFileName=$prevFileName;
		
		$this->loadConfigFile();
		$this->rootController=RootController::$___instance;
		
		static::$___instances[$this->gId]=&$this;
	}
	static public function ___getInstanceByGId($gId)
	{
		if (isset(static::$___instances[$gId]))
		{
			return static::$___instances[$gId];
		}
	}
	public function __destruct()
	{
		if(!defined('DEBUG'))
		{
			return;
		}
		$this->debugSaveConfigFile();
	}
	public function unsetInstanceReference()
	{
		unset(static::$___instances[$this->gId]);
	}
	public function getGId()
	{
		return $this->gId;
	}
	public function getInitPath()
	{
		return $this->initialisationScriptFileName;
	}
	protected function includeScript($scriptFileName, $_ARGS=null)
	{
		return include($scriptFileName);
	}
	/**/
	protected function loadConfigFile()
	{
		if(!isset(static::$___relativeRedirectUrl))
		{
			//static::$___relativeRedirectUrl=preg_replace('|'.getcwd().'|', '', realpath($_SERVER['DOCUMENT_ROOT']).$_SERVER['REQUEST_DIR']);
			
			static::$___relativeRedirectUrl=$_SERVER['REQUEST_DIR'];
		}
		
		foreach(array(
						static::$___cacheConfigDir.'/'.$this->gId.'.config.php', 
						static::$___cacheConfigDir.'/per-dir'.static::$___relativeRedirectUrl.'/'.$this->gId.'.config.php'
						) as $fileName)
		{

			if(is_file($fileName))
			{
				include($fileName);
			}
		}
	}
	public function onPing($actId, $phase, $targetGId, $httpInput, $targetHttpInput)
	{
		echo '<pre>'.$this->gId.' : '.__METHOD__.' ; phase : '.$phase.'<br>'.print_r($httpInput, true).'</pre><br>';
	}
	protected function debugSaveConfigFile($properties=null, $perDirProperties=null)
	{
		if(!$properties && !$perDirProperties)
		{
			return;
		}
		if(!static::$___cacheConfigDir)
		{
			die('No cache dir specified');
		}
		
		foreach(
				array(
					static::$___cacheConfigDir.'/'.$this->gId.'.config.php' => $properties, 
					static::$___cacheConfigDir.'/per-dir'.static::$___relativeRedirectUrl.'/'.$this->gId.'.config.php' => $perDirProperties
				) as $fileName=>$configProperties)
		{
			
			if(!$configProperties)
			{
				continue;
			}

			if(!is_dir(dirname($fileName)))
			{
				mkdir(dirname($fileName), 0777, true);
			}
			$fp=fopen($fileName, 'w');
			fputs($fp, '<?php'."\n");

			foreach($configProperties as $propertyName)
			{
				fputs($fp, '$this->'.$propertyName.'='.var_export($this->$propertyName, true).';'."\n");
			}

			fclose($fp);
		}
	}
	static public function ___debugUnsetInstancesReferences()
	{
		static::$___instances=null;
	}
}
