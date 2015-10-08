<?php

class FormController extends ViewController
{
	public function onSubmit($actId, $phase, $targetGId, $httpInput, $targetHttpInput)
	{
		echo 'SUBMITTING';
		//echo '<pre>';
		//print_r($_POST);
		//$this->data->relationshipPath->debugPrintData();
		$this->data->import($_POST['data'], array(\alib\model\RelationshipPath::IDX_LOADED => true));
		echo 'AFTER';
		//$this->data->relationshipPath->debugPrintData();
		//die();
		//return;
		$GLOBALS['dbg']=true;
		//echo '<pre>';
		try
		{
			//die('123');
			$this->data->saveAll();
			
		}
		catch(\alib\Exception2 $err)
		{
			
			//echo 'axxa'.$err->getCode();
			$this->view->setErrorCodes($err->getErrorCodes());
			//$err->throwIfErrors();
		}
		$GLOBALS['dbg']=false;
		
//		/echo '</pre>';
		
		//echo 'rpk: '.$this->data->rpk;
		//$this->data->relationshipPath->debugPrintData();
		//echo '</pre>';
	}
	protected function registerActions()
	{
		RootController::___getInstance()->registerAction($this->gId, 'actiune', 'onSubmit');
	}
	
}

