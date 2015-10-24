<?php

class FormController extends ViewController
{
	public function onSubmit($actId, $phase, $targetGId, $httpInput, $targetHttpInput)
	{
		return;
		//echo 'da';
		//print_r($httpInput);
		//return;
		//echo 'SUBMITTING';
		//echo '<pre>';
		//print_r($_POST);
		//$this->data->relationshipPath->debugPrintData();
		//print_r($this->data->contentItems->items()); die();
		//echo count($this->data->contentItems->items());die();
		$this->data->import($_POST['contentItemController']['data'], array(\alib\model\RelationshipPath::IDX_LOADED => true));
		//echo 'AFTER';
		
		//$this->data->relationshipPath->contentItems;
		//print_r($this->data->relationshipPath->paths); die('000');
		//die();
		//return;
		//$GLOBALS['dbg']=true;
		//echo '<pre>';
		try
		{
			//die('123');
			$this->data->saveAll();
			
		}
		catch(\alib\Exception2 $err)
		{
			
			//echo 'axxa'.$err->getCode();
			//$this->view->setErrorCodes($err->getErrorCodes());
			//$err->throwIfErrors();
		}
		//$GLOBALS['dbg']=false;
		//echo "AFTER SAVE\n";
		//echo '</pre>';
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

