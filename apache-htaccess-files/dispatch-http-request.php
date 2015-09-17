<?php

include(__DIR__.'/../../config/init.inc.php');

include_once(ALIB_DIR.'/mvc/views/RootView.class.php');
include_once(LIB_DIR.'/views/TestView.class.php');
include_once(LIB_DIR.'/controllers/TestController.class.php');
include_once(LIB_DIR.'/controllers/TestRootController.class.php');

/*
 * Dispatch the HTTP request to the appropriate controller and method script and/or action method
 */

TestRootController::___createInstance(
			ROOT_CONTROLLER_GID, 
			0,
			substr(__DIR__, strlen(realpath($_SERVER['DOCUMENT_ROOT']))),
			$_SERVER['REQUEST_URI'],
			VIEW_TEMPLATES_DIR.'/RootViewDefault.html.php' 
		)
		->dispatchHttpRequest();






