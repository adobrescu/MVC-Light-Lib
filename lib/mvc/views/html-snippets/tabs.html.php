<?php

	if(!($tabMenus=$this->getMenus(static::MENU_TAB)))
	{
		return;
	}
	$crtTab=$this->crtTab?$tabMenus[$this->crtTab]:reset($tabMenus);
	
?>
<ul>
<?php
	foreach($tabMenus as $tabMenu)
	{
?>
	<li><?=$this->formatTabMenu($tabMenu)?></li>
<?php
	}
?>
</ul>
<?php
	include($crtTab['filename']);
?>
