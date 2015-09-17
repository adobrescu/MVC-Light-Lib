<?php
	if(!$menus)
	{
		return;
	}
?>
<ul>
<?php
	foreach($menus as $flags=>$menu)
	{
?>
	<li><a href="<?=$menu['uri']?>" title="<?=$menu['title']?>"><?=$menu['caption']?></a></li>
<?php
	}
?>
</ul>