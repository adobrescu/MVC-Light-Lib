<?php

include_once(__DIR__.'/../../core/Object.class.php');

class Controller extends Object
{
	protected $views, $view;
	protected $data;//generic data (eg. a db record, data loaded from a file etc)
	
	public function formatCleanUri($path, $leadingSlash=true, $trailingSlash=false)
	{
		return preg_replace(array('/[\/]+/', '/\/$/', '/^[\/]/' ), array('/', $trailingSlash?'/':'', $leadingSlash?'/':''), ($leadingSlash?'/':'').$path.($trailingSlash?'/':''));
	}
	public function formatCleanPath($path)
	{
		return preg_replace('/[\/]+/', '/', $path);
	}
	public function resolvePathDots($path)
	{
		$resolvedPath=str_replace('/./', '/', $path);
		$dotsPos=$searchStartPos=0;
		
		while(true)
		{
			if(($dotsPos=strpos($resolvedPath, '/../', $searchStartPos))===false || $dotsPos===0)
			{
				break;
			}
			
			$prevSlashPos=strrpos($resolvedPath, '/', -(strlen($resolvedPath)-($dotsPos)+1));
			
			if(substr($resolvedPath, $prevSlashPos+1, $dotsPos-$prevSlashPos-1)=='.')
			{
				$searchStartPos=$dotsPos+1;
				continue;
			}
			
			$resolvedPath=substr($resolvedPath, 0, $prevSlashPos===false?0:$prevSlashPos+1).substr($resolvedPath, $dotsPos+4);
			
			
			
		}

		
		return $resolvedPath;
	}
	public function isCleanPath($path, $leadingSlash=true, $trailingSlash=false)
	{
		$pattern=($leadingSlash ? '^[^\/]|' : '').'[\/]{2,}'.($trailingSlash ? '|[^\/]$' : '');
		
		return !preg_match('/'.$pattern.'/', $path);
	}
		
	public function redirect($uri, $code=302)
	{
		$redirectCodes=array(
			301=>'Moved Permanently',
			302=>'Found');
		
		ob_end_clean();
		header('HTTP/1.1 '.$code.' '.$redirectCodes[$code], true, $code);
		header('Location: '.$uri, true, $code);
		exit();
	}
	
	
	/*action handlers*/
	public function onTestAction($actId, $phase, $targetGId, $httpInput, $targetHttpInput)
	{
		echo "\n\nphase: ".$phase."\n";
		//die('axxa'.$this->gId);
	}
}

