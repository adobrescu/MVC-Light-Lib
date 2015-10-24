<?php

include_once(__DIR__.'/Controller.class.php');

class ViewController extends Controller
{
	/**
	 * views
	 */
	public function addView($view, $flags, $pos)
	{
		$this->views[$viewGId=$view->getGId()]=$this->rootController->view->addView($view, $flags, $pos);
		//$this->views[$viewGId]->setData($this->data);
		
		if(!$this->view)
		{
			reset($this->views);
			$this->view=&$this->views[key($this->views)];
		}
		
		return $this->views[$viewGId];
	}
	/*
	 * Short way to include a script that creates a view and add the view
	 * to the controller
	 */
	public function addViewByScript($fileName, $flags, $pos, &$data)
	{
		include_once($fileName);
		
		$this->addView(
			Object::___getLastInstance(),
			$flags,
			$pos
			);
	}
	public function getView($viewGId='')
	{
		
		if($viewGId)
		{
			return isset($this->views[$viewGId])?$this->views[$viewGId]:null;
		}
		return $this->view;
	}
	public function getViews()
	{
		return $this->views;
	}
	/*menus*/
	public function addMethodMenu($methodUri, $flags, $params=null, $view=null)
	{
		
		if(!$view)
		{
			$view=$this->view;
		}
		return $view->addControllerMenu( $this->gId, 
				$methodUri, 
				array(
					'caption' => ($caption=ucwords(preg_replace('/[A-Z]/', ' \\0', str_ireplace('controllerID', '', str_replace('/', ' ', $this->gId.' '.str_replace('-', ' ', basename($methodUri)).($params?' with params':'')))))), 
					'title' => 'Click here for '.$caption 
				), 
				$flags, 
				$params);
	}
}