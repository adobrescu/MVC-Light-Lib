<?php

if(!($children=$this->flatFile->getChildren()))
{
	return;
}
?>
<ul class="menu2-ul clearfix">
<?php
	$i=1;
	foreach($children as $child)
	{
		$params=array('articleNum' => $i);
?>
	<li class="menu2-li clearfix">
		<h4><img src="<?=$this->rootController->documentRootUrl?>/images/<?=$child->getIconSrc()?>" style="float: left;"><a href="<?=static::$___documentRootUrl.$child->getProperty('uri')?>" class="menu2-a"><?=$child->getProperty('title', $params)?></a></h4>
		<?=$child->getProperty('leadin', $params);?>
	</li>
<?php
		$i++;
	}
?>
</ul>