<?php

include_once(__DIR__.'/../../core/Object.class.php');

class View extends Object
{
	const VIEW_HEADER=1; //Defines a header for the document or a section
    const VIEW_FOOTER=2; //Defines a footer for the document or a section
    const VIEW_NAV=4; //Defines navigation links in the document
    const VIEW_MAIN=8; //Defines the main content of a document
    const VIEW_ARTICLE=16; //Defines an article in the document
    const VIEW_ASIDE=32; //Defines content aside from the page content
    const VIEW_DETAILS=64; //Defines additional details that the user can view or hide
    const VIEW_FIGURE=128; //Defines self-contained content, like illustrations, diagrams, photos, code listings, etc.; <figcaption> 	Defines a caption for a <figure> element
    const VIEW_SECTION=256;//Defines a section in the document
	
	const VIEW_WIDE=512;
	
	const VIEW_LEFT=131072;
	const VIEW_RIGHT=262144;
	
	const MENU=1;
	const MENU_TAB=2;
	
	const ACT_CHANGE_TAB=1;
	
	public $title;
	protected $templateFileName;
	protected $data;
	protected $htmlTag='div';
	protected $views, $viewsQueue=array();
	protected $menus;
	protected $tabs, $crtTab;
	static protected $___documentRootUrl;
	
	public function __construct($gId, $gFlags, &$data, $templateFileName, $htmlTag='')
	{
		parent::__construct($gId, $gFlags);
		
		$this->data=&$data;
		$this->templateFileName=$templateFileName;
		
		if($htmlTag)
		{
			$this->htmlTag=$htmlTag;
		}
	}
	static public function ___setDocumentRootUrl($documentRootUrl)
	{
		static::$___documentRootUrl=$documentRootUrl;
	}
	public function setTemplateFileName($templateFileName)
	{
		$this->templateFileName=$templateFileName;
	}
	public function getTemplateFileName()
	{
		return $this->templateFileName;
	}
	public function show($htmlTag='', $attributes='class="view clearfix"', $showInnerHtmlOnly=false)
	{
		if($htmlTag && $this->htmlTag!=strtolower($htmlTag))
		{
			$this->htmlTag=strtolower($htmlTag);
		}
		
		foreach($this->viewsQueue as $flags=>$viewsQueueItems)
		{
			$this->sortViews($flags);
		}
		
		if(!$showInnerHtmlOnly)
		{
			
			$this->includeScript(__DIR__.'/html-snippets/open.html.php', array('attributes'=>$attributes) );
		}

		include($this->templateFileName);
		
		if(!$showInnerHtmlOnly)
		{
			$this->includeScript(__DIR__.'/html-snippets/close.html.php');
		}
	}
	/*views*/
	public function addView($view, $flags, $pos=null)
    {
		if(is_null($pos))
		{
			$pos=isset($this->viewsQueue[$flags])?count($this->viewsQueue[$flags]):0;
		}
		
		$this->viewsQueue[$flags][]=array(
			'view' => $view,
			'pos' => $pos
		);
		
        return $view;
    }
	
	static public function compareViewsPos($viewQueueItem1, $viewQueueItem2)
	{
		return $viewQueueItem1['pos']>$viewQueueItem2['pos'];
	}
	protected function sortViews($flags)
	{
		if(!isset($this->viewsQueue[$flags]))
		{
			return;
		}		
		if(count($this->viewsQueue[$flags])>1)
		{
			usort($this->viewsQueue[$flags], array('View', 'compareViewsPos'));
		}
		foreach($this->viewsQueue[$flags] as $viewsQueueItem)
		{
			$this->views[$flags][]=$viewsQueueItem['view'];
		}
	}
    protected function showViews($flags, $containerHtmlTag='', $containerAttributes='class="view clearfix"')
    {
		if(!isset($this->views[$flags]))
        {
			return;
		}
        
		foreach($this->views[$flags] as $view)
        {
            $view->show($containerHtmlTag, $containerAttributes);
        }
    }
	public function addTab($tabId, $tab, $tabTemplateFileName)
	{
		$tab['filename']=$tabTemplateFileName;
		$tab['id_menu']=$tabId;
		$this->addMenu($tab, static::MENU_TAB);
	}
	/* menus */
	public function addMenu($menu, $flags=null)
	{
		if(is_null($flags))
		{
			$flags=static::MENU;
		}
		if(isset($menu['id_menu']))
		{
			$this->menus[$flags][$menu['id_menu']]=$menu;
		}
		else
		{
			$this->menus[$flags][]=$menu;
		}
		
		return $menu;
	}
	public function addActionMenu($actId, $methodName, $menuText, $flags, $params=null)
	{
		$menuText['uri']=$this->getActionUri($actId, $methodName, $params);
		
		$this->addMenu($menuText, $flags);
	}
	public function addControllerMenu($controllerGId, $methodUri, $menuText, $flags, $params=null, $controllerParams=null, $actId=null, $methodName=null, $actParams=null)
	{
		$menuText['uri']=$this->rootController->getUri($controllerGId, $methodUri, $params, $controllerParams, $controllerGId, $actId, $methodName, $actParams);
		
		return $this->addMenu($menuText, $flags);//$this->getMethodMenu($methodFileName), $flags);
	}
	public function getMenus($flags=0)
	{
		return $flags==0 ? $this->menus: (isset($this->menus[$flags]) ? $this->menus[$flags] : null);
	}
	public function formatMenu($menu)
	{
		return '<a href="'.$menu['uri'].'" title="'.$menu['title'].'">'.$menu['caption'].'</a>';
	}
	public function formatTabMenu($menu)
	{
		return '<a href="'.$this->getActionUri(static::ACT_CHANGE_TAB, array('id_menu' => $menu['id_menu']) ).'">'.$menu['caption'].'</a>';
	}
	public function getActionUri($actId, $methodName, $params=null)
	{
		return $this->rootController->getActionUri($this->gId, $actId, $methodName, $params); 		
	}
	protected function includeHtmlSnippet($htmlSnippetFileName)
	{
		if(count($args=func_get_args())>1)
		{
			extract($args[1]);
		}
		include(__DIR__.'/html-snippets/menus-ul.html.php');
	}
	public function onPing2($actId, $phase, $targetGId, $httpInput, $targetHttpInput)
	{
		echo 'axxa:'.$phase.'<br>';
		//die('axxa'.$this->gId);
		//$this->crtTab=$targetHttpInput['id_menu'];
	}
}
