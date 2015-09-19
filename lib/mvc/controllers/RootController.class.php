<?php

include_once(__DIR__.'/ViewController.class.php');
include_once(__DIR__.'/../../misc/Directory.class.php');
include_once(__DIR__.'/../views/RootView.class.php');

class RootController extends ViewController
{
	const SCRIPT_EXTENSION_CONTROLLER='.controller.php'; /*controller scripts extension*/
	const SCRIPT_EXTENSION_METHOD='.php'; /*method scripts extension*/
	const SCRIPT_EXTENSION_LAYOUT='.layout.php';
	const SCRIPT_EXTENSION_VIEW='.view.php';
	
	/**
	* CSQ comes from "Controller Scripts Queue"
	*/
	const CSQ_CONTROLLER='controller';//0;
	const CSQ_METHODS='method';//1;
	const CSQ_FILENAME='filename';//2;
	const CSQ_IS_PROTECTED='is_protected';//3;
	const CSQ_CALL_URI='call_uri';//4;
	const CSQ_ROUTE_URI='route';//5;
	const CSQ_URI='uri';//6;
	const CSQ_ARG_URI='arg_uri';//7;
	const CSQ_RUN_BEFORE_CURRENT='run_before_current';//8;
	const CSQ_IS_CURRENT='is_current';//9;
	const CSQ_GID='gid';
	const CSQ_ARGS='args';//7;
	
	const PCSQ_BEFORE_METHOD=0;
	const PCSQ_BEFORE_ACTION=1;
	const PCSQ_ACTION=2;
	const PCSQ_AFTER_ACTION=3;
	
	const ACTID_NAME='actid';
	const ACT_DATA='data';
	const CONTROLLER_ARGS_PREFIX='ctrl';
	
	protected $controllerScriptIndexName='index'; /* -"- index file name*/
	
	static protected $___instance;
	
	protected $controllers=array();
	protected $serverDocumentRoots, $publicDocumentRoot;
	
	protected $publicDocumentRootLen;
	
	protected $documentRootUrl;
	protected $documentRootUrlLen;
	
	
	protected $layoutFileName='default';
	protected $uriFormat=0;
	
	protected $csq=array(); //Controller Scripts Queue
							//Keeps info about controller scripts that must run
							//Processed by processCSQ method
	protected $csqMaxIndex=-1;/*max value of $controllerScriptsQueue keys; gets incremented every time includeControllerScript is called*/
	
	protected $controllerScriptUris=array();
	protected $controllerLocations=array();
	protected $processCSQPhase;
	protected $httpInput=array();
	protected $actions;
	protected $trackedActions=array();
	
	protected $requestUri;
	protected $currentControllerGId;
	protected $inputVars;
	protected $currentCS=null;
		
	public function __construct($gId, $gFlags, $documentRootUrl, $requestUri, $rootViewTemplateFileName, $rootViewClassName='RootView')
	{
		if (static::$___instance)
		{
			throw new Exception('A RootController instance already exists');
		}
		
		static::$___instance=$this;
		
		session_start();
		$this->serverDocumentRoot=realpath($_SERVER['DOCUMENT_ROOT']);
		View::___setDocumentRootUrl($this->documentRootUrl=$documentRootUrl);
		
		$this->documentRootUrlLen=strlen($this->documentRootUrl);
		
		$this->publicDocumentRoot=$this->formatCleanUri($this->serverDocumentRoot.'/'.$documentRootUrl);
		$this->publicDocumentRootLen=strlen($this->publicDocumentRoot);
		
		chdir($this->publicDocumentRoot);
				
		$pathInfo=pathinfo($this->publicDocumentRoot);
		
		$this->requestUri=$requestUri;
		$_SERVER['REQUEST_DIR']='';
		
		//Object::$___autoGeneratedConfigDir=realpath($this->publicDocumentRoot.'/../../config/cache');
				
		$this->gId=$gId;
		
		$this->createRootView($rootViewClassName, $rootViewTemplateFileName);
		
				
		parent::__construct($gId, $gFlags, null);	
		
		if(defined('DEBUG'))
		{
			$this->debugLoadControllerScripts();
		}
	}
	protected function createRootView($rootViewClassName, $rootViewTemplateFileName)
	{
		$this->view=new $rootViewClassName($this->gId.'View', 0, $this->data, $rootViewTemplateFileName);
	}
	public function setLayout($layoutFileName, $templateFileName='')
	{
		$this->layoutFileName=$layoutFileName;
		if($templateFileName)
		{
			$this->view->setTemplateFileName($templateFileName);
		}
	}
	static public function ___createInstance($gId, $gFlags, $documentRootUrl, $requestUri, $rootViewTemplateFileName, $rootViewClassName='RootView')
	{
		if(static::$___instance)
		{
			return static::$___instance;
		}
		$calledClass=get_called_class();
		
		new $calledClass($gId, $gFlags, $documentRootUrl, $requestUri, $rootViewTemplateFileName, $rootViewClassName);
		
		return static::$___instance;
	}
	static public function ___getInstance()
	{
		return static::$___instance;
	}
	public function getDocumentRootUrl()
	{
		return $this->documentRootUrl;
	}
	public function getPublicDocumentRoot()
	{
		return $this->publicDocumentRoot;
	}
	public function getRequestUri()
	{
		return $this->requestUri;
	}
	public function dispatchHttpRequest()
	{
		$uriParts=parse_url($this->requestUri);
		
		if(!$this->isCleanPath($uriParts['path'], true, true))
		{
			if(defined('DEBUG'))
			{
				die('Dirty request uri: '.$uriParts['path']);
			}
			else
			{
				$this->redirect(
						$this->formatCleanUri($uriParts['path'], true, true).($uriParts['query']?'?'.$uriParts['query']:''),
						301);
			}
		}
		
		$this->httpInput=isset($_GET[static::ACTID_NAME])?$_GET[static::ACTID_NAME]:array();
		
		if(isset($_POST[static::ACTID_NAME]))
		{
			$this->httpInput+=$_POST[static::ACTID_NAME];
		}
		
		foreach($this->httpInput as $actId=>$targetArr)
		{
			foreach($targetArr as $targetGId=>$null)
			{
				if(isset($_GET[static::ACT_DATA][$actId][$targetGId]))
				{
					$this->httpInput[$actId][$targetGId]=$_GET[static::ACT_DATA][$actId][$targetGId];
				}
				else
				{
					$this->httpInput[$actId][$targetGId]=null;
				}
			}
		}
		
		$this->includeControllerScript(substr($this->requestUri, $this->documentRootUrlLen+1), $runAfterDispatch=false, $runDefaultMethod=true, $isHttpRequest=true);
		
		
		$this->includeScript($this->layoutFileName.static::SCRIPT_EXTENSION_LAYOUT);
		
		$this->processCSQ();
		
		$this->view->show();
	}
	public function mapPath2PublicUrl($path, $relative2Path='')
	{
		$path=$this->resolvePathDots($path);
		if($relative2Path)
		{
			$relative2Path=realpath($relative2Path);
		}
		return substr($path, $relative2Path?strlen($relative2Path):($this->publicDocumentRootLen));
	}
	public function mapPath2Url($path)
	{
		return str_replace($this->serverDocumentRoot, '', $path);
	}
	public function getRelativeUri($uri)
	{
		return preg_replace('|^'.preg_quote($this->documentRootUrl, '|').'\/|', '', $uri);
	}
	public function formatScriptUri($uri)
	{
		static $replacement;
		
		if(!$replacement)
		{
			$replacement=array(
								
								'/'.$this->controllerScriptIndexName.static::SCRIPT_EXTENSION_CONTROLLER.'/',
								'/'.$this->controllerScriptIndexName.static::SCRIPT_EXTENSION_METHOD.'/',
								''.static::SCRIPT_EXTENSION_CONTROLLER.'/',
								''.static::SCRIPT_EXTENSION_METHOD.'/',
								
								);
		}
		
		switch($this->uriFormat)
		{
			case 0:
				$uri=str_replace(
							$replacement,
							'/',
							$this->formatCleanUri($uri, true, true)
						);
				
				/*
				$uri=
						preg_replace('/'.static::SCRIPT_EXTENSION_METHOD.'$|'.
										static::SCRIPT_EXTENSION_METHOD.'\/|'.
										static::SCRIPT_EXTENSION_CONTROLLER.'$|'.
										static::SCRIPT_EXTENSION_CONTROLLER.'\/|'.
										'\/'.$this->controllerScriptIndexName.static::SCRIPT_EXTENSION_CONTROLLER.'$|'.
										'\/'.$this->controllerScriptIndexName.static::SCRIPT_EXTENSION_CONTROLLER.'\/|'.
										'\/'.$this->controllerScriptIndexName.static::SCRIPT_EXTENSION_METHOD.'$|'.
										'\/'.$this->controllerScriptIndexName.static::SCRIPT_EXTENSION_METHOD.'\//', 
										'/', $v=$this->formatCleanUri($uri, true, true));
				
				 */
				
				break;
		}
		
		return $uri;
	}
	
	public function isScriptPath($path, $extension)
	{
		static $scriptPathPattern=array();
		/*
		 * A. Build extension matching regexp pattern, if not already built
		 * B. Foreach known extension:
		 * 1. if it's exactly the extension received then do nothing, continue the loop
		 * 2. if it's not ending with the received extension, the same: continue the loop
		 * 3. it's ending with $extension, then substract the difference and append it to a subpattern 
		 * 
		 * C. The ending pattern contains the subpattern enclosed between "negative lookbehind" paranthesis: (?<!subpattern)
		 */
		//A.
		if (!isset($scriptPathPattern[$extension]))
		{
			$extensionLength=strlen($extension);
			$subPattern='';
			//B.
			foreach(array(static::SCRIPT_EXTENSION_CONTROLLER, static::SCRIPT_EXTENSION_METHOD, static::SCRIPT_EXTENSION_LAYOUT, static::SCRIPT_EXTENSION_VIEW) as $scriptExtension)
			{
				//if 1. or 2.
				if ($scriptExtension==$extension || substr($scriptExtension, -$extensionLength)!=$extension)
				{
					continue;
				}
				
				$subPattern.=($subPattern?'|':'').preg_quote(substr($scriptExtension, 0, strlen($scriptExtension)-$extensionLength), '/');
				
			}
			//C.
			$scriptPathPattern[$extension]='/'.($subPattern?'(?<!'.$subPattern.')':'').preg_quote($extension, '/').'$/';
		}
		
		
		/* A. Look for a file that matches:
		 * 1. the exact received path
		 * 2. the path + received extension
		 * 3. a default file within $path dir
		 * 
		 * 5. any matched file must also match its regexp pattern built above
		 * 
		 */
		//A.
		
		
		
		if ((
			
				is_file($controllerScriptFileName=$path) //1.
				||
				is_file($controllerScriptFileName=$path.$extension)//2.
				||
				is_file($controllerScriptFileName=$path.'/'.$this->controllerScriptIndexName.$extension)//3.
				||
				is_file($controllerScriptFileName=substr($path, 0, strpos($path, '.', strrpos($path, '/')) ).$extension)
			)
			&& preg_match($scriptPathPattern[$extension], $controllerScriptFileName)//5.
			)
        {
            return $this->formatCleanPath($controllerScriptFileName);
        }
		
		
	}
	public function getScriptPathComponents($path, $extension, $checkProtectedMethods, $stopPath=null)
	{	
		$path=$this->resolvePathDots($path);
				
		/* 1. Build $uri path (document root + uri)
		 * 2. For each sub-path of the obtained path (starting with the deepest = the path itself) try to find a controller script:
		 * 
		 * - sub-path ends with $extension
		 * - sub-path + $extension
		 * - sub-path is a dir and it contains index+$extension
		 * - sub-path is a dir and it contains only one file with the extension $extension
		 */
		if($path && $path[0]=='/')
		{
			$path=substr($path, $this->publicDocumentRootLen+1);
		}
		$searchPath=$this->publicDocumentRoot;
		//foreach(array($this->publicDocumentRoot, $this->protectedDocumentRoot) as $searchPath)
		{
			$pos=-1;
			$stopPos=strlen(realpath($stopPath?$stopPath:$searchPath));
			$scriptFileName='';
			
			if($path && $path[0]=='/')
			{
				$realPath=$startPath=$path;
			}
			else
			{
				$realPath=$startPath=$searchPath.($path?'/'.$path:'');
			}
			
			do
			{
				if( $scriptFileName=$this->isScriptPath($realPath, $extension) )
				{
					break;
				}

				if(($pos=strrpos($realPath, '/'))===false || $pos<$stopPos)
				{
					break;
				}

				$realPath=substr($realPath, 0, $pos);

			}
			while(true);
			
			
		}
		
		
		if(!(realpath($scriptFileName)))
		{
			return false;
		}
		
		
		
		$relFileName=substr($scriptFileName, strlen($searchPath)+1);//($isProtected?'../protected/':'').substr($scriptFileName, strlen($searchPath)+1);
		$ret=array(
			static::CSQ_FILENAME => $relFileName,
			static::CSQ_ROUTE_URI => $this->getControllerRoute($scriptFileName, false, $extension),
			//static::CSQ_CALL_URI => $this->mapPath2PublicUrl($path, $stopPath),
			static::CSQ_URI => $this->formatScriptUri($this->mapPath2PublicUrl($scriptFileName, $stopPath)),
			static::CSQ_ARG_URI => substr($startPath, strlen($realPath)+1),
			static::CSQ_IS_PROTECTED => false//$isProtected
		);
		return $ret;
		
	}
	
	public function dirname($path)
	{
		if(($dirname=dirname($path))=='.')
		{
			return '';
		}
		
		return $dirname;
	}
	public function getControllerGIdByRouteUri($controllerRouteUri)
	{
		foreach($this->controllerScriptUris as $gId=>$controllerInfo)
		{
			if(isset($controllerInfo[static::CSQ_ROUTE_URI]) && $controllerInfo[static::CSQ_ROUTE_URI]==$controllerRouteUri)
			{
				return $gId;
			}
		}
	}
	/**
	 * parseInputVars
	 * 
	 * 
	 * @param type $queryStringOrVarsArray
	 * @param type $controllerGId
	 */
	protected function parseInputVars($queryStringOrVarsArray, $controllerGId='', &$controller)//&$methodVars, &$controllerVars)
	{
		/*
		 * 1. Parse query string into variables.
		 * 2. 
		 * For each variable:
		 *	if it is not array then add it in included controller $methodVars entry.
		 *	if its name is included controller gId, the same, put it in included controller $methodVars entry.
		 *	if its name eq a controller gId, put the var in that controller $methodVars entry
		 *	else put it in included controller $methodVars entry.
		 */
		if(!isset($controller[static::CSQ_METHODS][static::CSQ_ARGS]) || !is_array($controller[static::CSQ_METHODS][static::CSQ_ARGS]))
		{
			$controller[static::CSQ_METHODS][static::CSQ_ARGS]=array();
		}
		if(!isset($controller[static::CSQ_CONTROLLER][static::CSQ_ARGS]) || !is_array($controller[static::CSQ_CONTROLLER][static::CSQ_ARGS]))
		{
			$controller[static::CSQ_CONTROLLER][static::CSQ_ARGS]=array();
		}
		if(!is_array($queryStringOrVarsArray))
		{
			parse_str($queryStringOrVarsArray, $queryStringOrVarsArray);
		}
		foreach($queryStringOrVarsArray as $getVarName=>$getVarValue)
		{
			if($getVarName==static::ACTID_NAME || $getVarName==static::ACT_DATA)
			{
				continue;
			}
			if(!is_array($getVarValue))
			{
				$controller[static::CSQ_METHODS][static::CSQ_ARGS][$getVarName]=$getVarValue;
				continue;
			}
				
			if(isset($this->controllerScriptUris[$getVarName]))
			{//GET var name is a controller gid
				if($getVarName==$controllerGId)
				{
					if(isset($getVarValue[static::CONTROLLER_ARGS_PREFIX]))
					{
						$controller[static::CSQ_CONTROLLER][static::CSQ_ARGS]=$getVarValue[static::CONTROLLER_ARGS_PREFIX];
						unset($getVarValue[static::CONTROLLER_ARGS_PREFIX]);
					}
					$controller[static::CSQ_METHODS][static::CSQ_ARGS]+=$getVarValue;
					
				}
				else
				{
					
					if(!isset($this->inputVars[$getVarName]))
					{
						$this->inputVars[$getVarName]=array();
					}
					
					$this->inputVars[$getVarName]+=$getVarValue;
				}
				
				
				continue;
			}
				
			$controller[static::CSQ_METHODS][static::CSQ_ARGS][$getVarName]=$getVarValue;
		}
	}
	/**
	 * getScriptCallComponents
	 * 
	 * @param type $scriptFileName
	 * @param type $checkProtectedMethods
	 * 
	 * Find script path controller and method
	 */
	public function getScriptCallComponents($scriptFileName, $runDefaultMethod, $checkProtectedMethods)
	{
		if(!($controllerPathComponents=$this->getScriptPathComponents($scriptFileName, static::SCRIPT_EXTENSION_CONTROLLER, $checkProtectedMethods)))
		{
			return;
		}
		$controllerPathComponents[static::CSQ_GID]=$this->getControllerGIdByRouteUri($controllerPathComponents[static::CSQ_ROUTE_URI]);
		$pathInfo=pathinfo($controllerPathComponents[static::CSQ_FILENAME]);
		$methodScriptComponents=$this->getScriptPathComponents($pathInfo['dirname'].'/'.$controllerPathComponents[static::CSQ_ARG_URI], static::SCRIPT_EXTENSION_METHOD, $checkProtectedMethods, $pathInfo['dirname']);
		
		if( $runDefaultMethod && (
				( !$controllerPathComponents[static::CSQ_ARG_URI] 
				|| 
				!($methodScriptComponents=$this->getScriptPathComponents($pathInfo['dirname'].'/'.$controllerPathComponents[static::CSQ_ARG_URI], static::SCRIPT_EXTENSION_METHOD, $checkProtectedMethods, $pathInfo['dirname']))
			)
			&& is_file($defaultScriptMethod=$pathInfo['dirname'].'/'.substr($pathInfo['basename'], 0, -strlen(static::SCRIPT_EXTENSION_CONTROLLER)).static::SCRIPT_EXTENSION_METHOD))
				)
		{
			$methodScriptComponents[static::CSQ_FILENAME]=$defaultScriptMethod;
			$methodScriptComponents[static::CSQ_ARG_URI]=$controllerPathComponents[static::CSQ_ARG_URI];
			$methodScriptComponents[static::CSQ_URI]='';
			$methodScriptComponents[static::CSQ_IS_PROTECTED]=$controllerPathComponents[static::CSQ_IS_PROTECTED];
		}
		$methodScriptComponents[static::CSQ_URI]=$controllerPathComponents[static::CSQ_URI].''.substr($methodScriptComponents[static::CSQ_URI], 1);
		
		$methodScriptComponents[static::CSQ_ROUTE_URI]=$controllerPathComponents[static::CSQ_ROUTE_URI].substr($methodScriptComponents[static::CSQ_ROUTE_URI], 
				strlen(dirname($controllerPathComponents[static::CSQ_ROUTE_URI])));
		return array(
			static::CSQ_CONTROLLER => $controllerPathComponents,
			static::CSQ_METHODS => $methodScriptComponents
		);
	}
	/**
	 * 
	 * @param type $scriptFileName
	 * @param type $runBeforeDispatch - when processing calls queue, the controllers called with $runBeforeDispatch=true are included before current request controller
	 *									Usefull when you want to include global contollers (controllers that are most probably referenced by other controllers - eg. a global user object, shoppingcart etc)
	 * @param type $runDefaultMethod - if true and no method specified in the url then it tries to find a method with the same file basename as the controller (eg. for ctrl.controller.php the default method would be ctrl.php)
	 *									Usefull when you want to include a controller that has a default method but you don't want to call that method (all you need is that controller instance)
	 * @param type $isHttpRequest - if true then the call is coming from dispatchHttpRequest
	 *								Not used yet, but it might be usefull if some controller methods are located
	 *								outside webroot (some kind of "protected" methods"):
	 *								if the call is from dispatchHttpRequest then no protected methods are called.
	 *								This might be usefull when you want to "hide" methods that creates 
	 *								small HTML snippets (eg. shopping cart content bar, quick login form etc)
	 * @return boolean
	 */
	
	
	public function getCurrentCS()
	{
		return $this->currentCS;
	}
	public function includeControllerScript($scriptFileName, $runBeforeDispatch=false, $runDefaultMethod=true, $isHttpRequest=false)
	{	
		
		$uriParts=parse_url($scriptFileName);
		$scriptFileName=$uriParts['path'];
		
		$callComponents=$this->getScriptCallComponents($scriptFileName, $runDefaultMethod, !$isHttpRequest);
		
		if (!$callComponents)
		{
			if($isHttpRequest)
			{
				throw new Exception(__METHOD__.'Cannot include controller script: '."\n".$scriptFileName);
				
			}
			else
			{
				die(__METHOD__.': controller script not found: '.$scriptFileName);
			}
		}
		
		$controllerPathComponents=$callComponents[static::CSQ_CONTROLLER];
		$methodScriptComponents=$callComponents[static::CSQ_METHODS];
		
		$this->csqMaxIndex++;
				
		if(!defined('DEBUG'))
		{
			//check if the request is valid (it was requested while DEBUG was set)	
		}
		
		/*
		$controllerPathComponents=$this->getScriptPathComponents($scriptFileName, static::SCRIPT_EXTENSION_CONTROLLER, !$isHttpRequest, true);
		
		$pathInfo=pathinfo($controllerPathComponents[static::CSQ_FILENAME]);
		$methodScriptComponents=$this->getScriptPathComponents($pathInfo['dirname'].$controllerPathComponents[static::CSQ_ARG_URI], static::SCRIPT_EXTENSION_METHOD, !$isHttpRequest, false, $pathInfo['dirname']);
		
		if( $runDefaultMethod && (
				( !$controllerPathComponents[static::CSQ_ARG_URI] 
				|| 
				!($methodScriptComponents=$this->getScriptPathComponents($pathInfo['dirname'].$controllerPathComponents[static::CSQ_ARG_URI], static::SCRIPT_EXTENSION_METHOD, !$isHttpRequest, false, $pathInfo['dirname']))
			)
			&& is_file($defaultScriptMethod=$pathInfo['dirname'].'/'.substr($pathInfo['basename'], 0, -strlen(static::SCRIPT_EXTENSION_CONTROLLER)).static::SCRIPT_EXTENSION_METHOD))
				)
		{
			$methodScriptComponents[static::CSQ_FILENAME]=$defaultScriptMethod;
			$methodScriptComponents[static::CSQ_ARG_URI]=$controllerPathComponents[static::CSQ_ARG_URI];
			$methodScriptComponents[static::CSQ_URI]='';
			$methodScriptComponents[static::CSQ_IS_PROTECTED]=$controllerPathComponents[static::CSQ_IS_PROTECTED];
		}
		*/
		
		if($isHttpRequest)
		{
			$this->currentControllerGId=$controllerPathComponents[static::CSQ_GID];
		}

		
				
		if(!isset($this->csq[$this->csqMaxIndex][static::CSQ_CONTROLLER]))
		{
			$this->csq[$this->csqMaxIndex][static::CSQ_CONTROLLER]=$controllerPathComponents+
						array(static::CSQ_RUN_BEFORE_CURRENT => $runBeforeDispatch, static::CSQ_IS_CURRENT => $isHttpRequest);
		}
		elseif($runBeforeDispatch)
		{
			$this->csq[$this->csqMaxIndex][static::CSQ_CONTROLLER][static::CSQ_RUN_BEFORE_CURRENT]=$runBeforeDispatch;
		}
		if($isHttpRequest)
		{
			$this->currentCS=&$this->csq[$this->csqMaxIndex];
		}
		
		$methodScriptComponents[static::CSQ_RUN_BEFORE_CURRENT]=$runBeforeDispatch;
		$methodScriptComponents[static::CSQ_IS_CURRENT]=$isHttpRequest;
		
		$this->csq[$this->csqMaxIndex][static::CSQ_METHODS]=
				$methodScriptComponents;
		
	
		
		if($isHttpRequest && is_file($this->csqMaxIndex.static::SCRIPT_EXTENSION_LAYOUT))
		{
				$this->setLayout($this->csqMaxIndex);
		}
		
		if(isset($uriParts['query']))
		{
			$this->parseInputVars( $uriParts['query'], $controllerPathComponents[static::CSQ_GID], $this->csq[$this->csqMaxIndex]);//[static::CSQ_METHODS]['args'], $this->csq[$this->csqMaxIndex][static::CSQ_CONTROLLER]['args']);
			
		}
		
	}
	
	/**
	 * processCSQ
	 * 
	 * Process controller scripts queue
	 * 
	 */
	protected function processCSQ()
	{
		//print_r($this->csq); die();
		
		$this->parseInputVars($_POST, $this->currentControllerGId, $this->currentCS);//[static::CSQ_METHODS]['args'], $this->currentCS[static::CSQ_CONTROLLER]['args']);
		
		$this->trackedActions=array();
		
		for($this->processCSQPhase=2; $this->processCSQPhase>=0; $this->processCSQPhase--)
		{
			foreach($this->csq as $this->controllerRoute=>$this->controllerScriptInfo)
			{	
				$controller=null;
				$controllerGId=$this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_GID];
				
				if ( (!$this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_IS_CURRENT] && $this->processCSQPhase==1)
					|| ($this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_IS_CURRENT] && $this->processCSQPhase!=1) )
				{
					continue;
				}
				
				if(
					($this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_RUN_BEFORE_CURRENT]*2==$this->processCSQPhase)	
					||
					$this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_IS_CURRENT]
					)
				{
					if (isset($this->controllers[$controllerGId]))
					{
						$controller=$this->controllers[$controllerGId];
					}
					else
					{
						try
						{
							$this->args=isset($this->inputVars[$controllerGId]) ? $this->inputVars[$controllerGId] : array();
							if(isset($this->controllerScriptInfo[static::CSQ_CONTROLLER]['args']))
							{
								$this->args+=$this->controllerScriptInfo[static::CSQ_CONTROLLER]['args'];
							}
							$controller=$this->controllers[$controllerGId]=$this->includeScript($this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_FILENAME], $this->args);
							$this->args=array();
						}
						catch(Exception $err)
						{
							//exception on controller instantiation; skip its method
							continue;
						}
					}
					if(defined('DEBUG') && !is_a($controller, 'Controller'))
					{
						die('Controller scripts must return a <b>Controller</b> instance. <br>Controller script: '.$this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_FILENAME]);
					}
				}
				else
				{
					continue;
				}
				if(isset($this->controllerScriptInfo[static::CSQ_METHODS]))
				{
					$this->methodScriptInfo=$this->controllerScriptInfo[static::CSQ_METHODS];
					if(
						($this->methodScriptInfo[static::CSQ_RUN_BEFORE_CURRENT]*2==$this->processCSQPhase)
						||
						$this->controllerScriptInfo[static::CSQ_CONTROLLER][static::CSQ_IS_CURRENT]
						)
					{
						if (isset($this->methodScriptInfo[static::CSQ_FILENAME]) && is_file($this->methodScriptInfo[static::CSQ_FILENAME]))
						{
							if(isset($this->controllerScriptInfo[static::CSQ_METHODS][static::CSQ_ARGS]))
							{
								$this->args+=$this->controllerScriptInfo[static::CSQ_METHODS][static::CSQ_ARGS];
							}
							$controller->includeScript($this->methodScriptInfo[static::CSQ_FILENAME], $this->args);
							$this->args=array();
						}
					}
					
					/*dispatch actions to controller before dispatching to the target view*/
					$this->dispatchActions($controller, $this->httpInput);
				}
				if($this->processCSQPhase==1) //current controller included, skip to next phase
				{
					break;
				}
			}
		}
	}
	
	protected function dispatchActions($controller, $actionsInput)
	{ 
		$controllerGId=$controller->getGId();
		
		foreach($actionsInput as $actId=>$targetArr)
		{
			foreach($targetArr as $targetGId=>$targetData)
			{
				if(isset($this->trackedActions[$actId][$targetGId]) || !($target=Object::___getInstanceByGId($targetGId)))
				{
					continue;
				}
				
				$this->trackedActions[$actId][$targetGId]=true;
				$methodName=$this->actions[$actId][$targetGId][0];
				
				if($targetGId!=$controllerGId)
				{
					@call_user_func_array (  array($controller,$methodName ) , array($actId, static::PCSQ_BEFORE_ACTION, $targetGId, $targetData, $targetData) );
				}
				
				$target->$methodName($actId, static::PCSQ_ACTION, $targetGId, $targetData, $targetData);
				
				if($targetGId!=$controllerGId)
				{
					@call_user_func_array (  array($controller,$methodName ) , array($actId, static::PCSQ_AFTER_ACTION, $targetGId, $targetData, $targetData) );
				}
				
			}
		}
	}
	
	protected function getControllerRoute($fullPath, $isProtected, $extension)
	{
		$fullPath=$this->resolvePathDots($fullPath);
		
		return $route=substr($fullPath, $this->publicDocumentRootLen+1, -strlen($extension));
	}
	
	public function getUri($controllerGId, $methodUriOrFileName, $params, $controllerParams, $targetGId, $actId, $methodName, $actionParams, $relative2DocumentRootUrl=false)
	{
		return (
				$controllerGId ?
				$this->getControllerMethodUri($controllerGId, $methodUriOrFileName, $params, $controllerParams, $relative2DocumentRootUrl)
				:
				($relative2DocumentRootUrl?substr($this->requestUri, $this->documentRootUrlLen):$this->requestUri)
				).
				($actId?($params?'&amp;':'?').$this->getActionQueryString($targetGId, $actId, $actionParams):'');
	}
	public function getActionQueryString($targetGId, $actId, $params)
	{
		return $this->getActionNameQueryString($targetGId, $actId).$this->getActionParamsQueryString($targetGId, $actId, $params);
	}
	public function getActionNameQueryString($targetGId, $actId)
	{
		return http_build_query(
					array(
						static::ACTID_NAME => array( $actId => array ($targetGId=>1))
					),
					null,
					'&amp;'
				);
	}
	public function getActionParamsQueryString($targetGId, $actId, $params)
	{
		return (is_array($params)?'&amp;'.http_build_query(array('data['.$actId.']['.$targetGId.']'=>$params), null, '&amp;'):'');
	}
	public function registerAction($targetGId, $actId, $methodName)
	{
		$this->actions[$actId][$targetGId][0]=$methodName;
	}
	public function getActionUri($targetGId, $actId, $methodName, $actionParams=null)
	{
		return $this->getUri($controllerGId='', $methodFileName='', $params=null, $controllerParams=null, $targetGId, $actId, $methodName, $actionParams, $relative2DocumentRootUrl=false);
	}
	public function getControllerMethodUri($controllerGId, &$methodUriOrFileName, $params=null, $controllerParams=null, $relative2DocumentRootUrl=false)
	{		
		
		//@fixme
		//when $methodUriOrFileName is a full path filename, $controllerPath is wrong
				
		
		$controllerPath=$this->publicDocumentRoot.$this->controllerScriptUris[$controllerGId][static::CSQ_URI];
		$uri=$this->formatScriptUri(
					$this->controllerScriptUris[$controllerGId][static::CSQ_URI].'/'.($methodUriOrFileName=str_replace($controllerPath, '', $methodUriOrFileName.'/')));
		
		
		if(!isset($this->controllerScriptUris[$controllerGId]))
		{
			
			die(__METHOD__.' controller not set '.$controllerGId);
		}
		
		if($controllerParams)
		{
			$controllerParams=array(static::CONTROLLER_ARGS_PREFIX => $controllerParams);
		}
		else
		{
			$controllerParams=array();
		}
		if(!$params)
		{
			$params=array();
		}
		
		$allParams=$params+$controllerParams;
		
		
		
		
		$uri .=	($allParams?'?'.http_build_query(array($controllerGId=>$allParams), null, '&amp;'):'');
		
		return (
				!$relative2DocumentRootUrl
				?
				$this->documentRootUrl
				:
				'').$uri;
	}
	public function uri($uri)
	{
		return $uri;	
	}
	/*debugging mode methods (DEBUG constant defined)*/
	public function debugLoadControllerScripts()
	{
		
		$layoutFileName=$this->layoutFileName;
		$templateFileName=$this->view->getTemplateFileName();
		$checkProtectedMethods=false;
		foreach(array($this->publicDocumentRoot) as $dirName)
		{
			$dir=new Directory2($dirName);
			if($controllerScripts=$dir->getTree('*'.static::SCRIPT_EXTENSION_CONTROLLER))
			{
				foreach($controllerScripts as $controllerScript)
				{
					
					$controllerGId=$this->debugGetControllerGIdFromFile($controllerScript);
						
					$scriptPathComponents=$this->getScriptPathComponents($controllerScript, static::SCRIPT_EXTENSION_CONTROLLER, $checkProtectedMethods, $stopPath=null);
					
					$this->controllerScriptUris[$controllerGId]['filename']=$scriptPathComponents[static::CSQ_FILENAME];
					$this->controllerScriptUris[$controllerGId][static::CSQ_ROUTE_URI]=$scriptPathComponents[static::CSQ_ROUTE_URI];
					$this->controllerScriptUris[$controllerGId][static::CSQ_URI]=$scriptPathComponents[static::CSQ_URI];
				}
			}
			$checkProtectedMethods=true;
		}
			
		$this->setLayout($layoutFileName, $templateFileName);
		
		return $this->controllerScriptUris;
	}
	public function debugGetControllerScriptUris()
	{
		return $this->controllerScriptUris;
	}
	protected $langUris;
	public function debugGetLangUris()
	{
		return $this->langUris;
	}
	/**
	 * debugGetControllerGIdFromFile
	 * Finds a new created controller from a script
	 * @param type $controllerScript
	 */
	protected function debugGetControllerGIdFromFile($controllerScript)
	{
		$source=file_get_contents($controllerScript);
		$allTokens=token_get_all($source);
		
		//eat white spaces
		foreach($allTokens as $token)
		{
			if(is_array($token) && token_name($token[0])=='T_WHITESPACE')
			{
				continue;
			}
			$tokens[]=$token;
		}
		
		//first, find the "return" statement
		//assume that is only one return statement that return a new controller instance
		//and there are 2 forms:
		// return new Controler(...)
		// and:
		// $c=new Controller(...)
		//return $c
		
		$returnIndex=-1;
		$newIndex=-1;
		$varIndex=-1;
		$varName='';
		$controllerGId='';
		foreach($tokens as $i=>$token)
		{
			if(!is_array($token))
			{
				continue;
			}
			$tokenName=token_name($token[0]);

			if($tokenName=='T_RETURN')
			{
				$returnIndex=$i;
				continue;
			}
			if($returnIndex!=-1)//return found see what comes next
			{
				switch($tokenName)
				{
					case 'T_NEW':
						$newIndex=$i+1;
						break;
					case 'T_VARIABLE':
						if(strtolower($token[1])=='$this')
						{
							$controllerGId=$this->gId;
						}
						else
						{
							$varIndex=$i-1;
							$varName=$token[1];
						}
						break;
					
				}
				if($newIndex!=-1 || $varIndex!=-1)
				{
					break;
				}
			}
		}
		
		if($varName!=-1)
		{
			//find last $var=...
			for($i=$varIndex; $i>0; $i--)
			{
				if(!is_array($tokens[$i]))
				{
					continue;
				}
				$tokenName=token_name($tokens[$i][0]);
				if($tokenName=='T_VARIABLE' && $tokens[$i][1]==$varName)
				{//var found
				 //verifica daca este o atribuire
					if(!is_array($tokens[$i+1]) && $tokens[$i+1]=='=' && token_name($tokens[$i+2][0])=='T_NEW')
					{
						$newIndex=$i+3;
					}
				}
			}
		}
		if($newIndex!=-1)
		{
			$pFound=false;
			$gIdToken=$tokens[$newIndex+2];
			$gIdTokenName=token_name($gIdToken[0]);
			
			if($gIdTokenName=='T_VARIABLE')//pattern: new Controller($var='string')...)
			{								//or: new Controller($var=CONSTANT)...)
				$gIdToken=$tokens[$newIndex+4];
				$gIdTokenName=token_name($gIdToken[0]);
			}
			switch($gIdTokenName)
			{
				case 'T_CONSTANT_ENCAPSED_STRING': //pattern: new Controller($var='string')...)
					eval('$controllerGId='.$gIdToken[1].';');
					break;
				case 'T_STRING': //pattern: new Controller($var=CONSTANT)...)
					$cts=get_defined_constants();
					$controllerGId=$cts[$gIdToken[1]];
					break;
				
			}			
		}
		if(!$controllerGId)
		{
			die(__METHOD__.': couldn\'t find controller gId in '.$controllerScript);
		}
		return $controllerGId;
	}
	protected function debugSaveConfigFile($properties=null, $perDirProperties=null)
	{ 
		
		foreach($this->csq as $ctrlInfo)
		{
			if(!isset($ctrlInfo[static::CSQ_METHODS]))
			{
				continue;
			}
			//foreach($ctrlInfo[static::CSQ_METHODS] as $methodInfo)
			$methodInfo=$ctrlInfo[static::CSQ_METHODS];
			{
				
				if(!isset($methodInfo[static::CSQ_URI]))
				{
					$methodInfo[static::CSQ_URI]='';
				}
				$this->controllerScriptUris[$ctrlInfo[static::CSQ_CONTROLLER][static::CSQ_GID]][static::CSQ_METHODS][$methodInfo[static::CSQ_URI]]=1;
			}
		}
		
		parent::debugSaveConfigFile(array('controllerScriptUris', 'actions'), array('controllerLocations' ));
	} // te iubesc mult 
	static public function ___debugUnsetInstance()
	{
		Object::___debugUnsetInstancesReferences();
		static::$___instance=null;
	}
	public function debugGetCSQ()
	{
		return $this->csq;
	}
}
