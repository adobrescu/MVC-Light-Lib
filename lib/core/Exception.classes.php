<?php

namespace alib;

class Error
{
	public $code, $context;

	public function __construct ($code, $context=null)
	{
		$this->code=$code;
		$this->context=$context;
	}
}
class Exception2 extends \Exception
{
	public $exceptions;
	
	public function  __construct($code=null, $context=null)
	{
		if (!is_null($code))
		{
			$this->addException(new Error($code, $context));
		}
	}
	public function addException($exception, $row=0)
	{
		if (is_a($exception, '\alib\core\Exception'))
		{
			if (is_array($exception->exceptions))
			{
				foreach ($exception->exceptions as $row2=>$errors)
				{
					foreach ($errors as $err)
					{
						$this->exceptions[$row2][]=$err;
					}
				}
			}
		}
		else
		{
			$this->exceptions[$row][]=$exception;
		}
	}
	public function addError($errCode, $context=null, $row=0)
	{
		$this->addException(new Error($errCode, $context), $row);
	}
	
	public function throwIfErrors()
	{
		if (!is_array($this->exceptions))
		{
			return;
		}
			
		throw ($this);
	}
	
	public function hasErrors()
	{
		return is_array($this->exceptions);
	}
	public function hasError($code)
	{
		foreach($this->exceptions as $row=>$errors)
		{
			foreach($errors as $err)
			{
				if ($err->code==$code)
				{
					return true;
				}
			}
		}
		return false;
	}
	public function &getError($errCode, $row=0)
	{
		if (!is_array($this->exceptions[$row]))
		{
			return null;
		}
		foreach ($this->exceptions[$row] as $idx=>$err)
		{
			if ($err->code==$errCode)
			{
				return $this->exceptions[$row][$idx];
			}
		}

		return null;
	}
	public function getErrorCodes()
	{
		foreach($this->exceptions as $row=>$errors)
		{
			foreach($errors as $err)
			{
				$errorCodes[$row][]=$err->code;
			}
		}
		return $errorCodes;
	}
}
