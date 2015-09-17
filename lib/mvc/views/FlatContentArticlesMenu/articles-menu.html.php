<?php

if(!($children=$this->flatFile->getChildren()))
{
	return;
}
?>
<a href="#" class="nav-menu-icon" onclick="elementAddClassName(this.parentNode, 'open', !elementHasClassName(this.parentNode, 'open')); return false;"></a>
<ul class="menu-ul clearfix">
<?php
	foreach($children as $child)
	{
?>
		<li class="menu-li"><a href="<?=static::$___documentRootUrl.$child->getProperty('uri')?>" class="menu-a"><span class="menu-highlight"></span><?=$child->getProperty('title')?></a></li>
<?php
	}
?></ul>