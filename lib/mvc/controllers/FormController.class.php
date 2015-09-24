<?php

class FormController extends ViewController
{
	public function onSubmit($actId, $phase, $targetGId, $httpInput, $targetHttpInput)
	{
		//echo '<pre>';
		//print_r($_POST);
		//$this->data->relationshipPath->debugPrintData();
		$this->data->import($_POST['data'], array(\alib\model\RelationshipPath::IDX_LOADED => true));
		
		//$this->data->relationshipPath->debugPrintData();
		$GLOBALS['dbg']=true;
		echo '<pre>';
		try
		{
			$this->data->saveAll();
		}
		catch(\alib\Exception2 $err)
		{
			//die('axxa');
			$this->view->setErrorCodes($err->getErrorCodes());
		}
		echo '</pre>';
		
		//echo 'rpk: '.$this->data->rpk;
		//$this->data->relationshipPath->debugPrintData();
		//echo '</pre>';
	}
	protected function registerActions()
	{
		RootController::___getInstance()->registerAction('userController', 'actiune', 'onSubmit');
	}
	
}

