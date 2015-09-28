<?php

include_once(__DIR__.'/View.class.php');

class Form extends View
{
	protected $errorCodes;
	
	public function setErrorCodes($errorCodes)
	{
		$this->errorCodes=$errorCodes;
	}
	
	protected function formatErrors()
	{
		if(!$this->errorCodes)
		{
			return;
		}
		foreach($this->errorCodes as $row=>$errorCodes)
		{
			foreach($errorCodes as $errorCode)
			{
				$errors[$errorCode]=$errorCode;
			}
		}
		return $errors;
	}
	
	public function debugGetErrorCodes()
	{
		return $this->errorCodes;
	}
}