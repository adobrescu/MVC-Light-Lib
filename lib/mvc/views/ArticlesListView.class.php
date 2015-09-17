<?php

include_once(__DIR__.'/View.class.php');

class ArticleView extends View
{
	protected $htmlTag='article';
	
	public function formatLink($article)
	{
		return '<a href="'.static::$___documentRootUrl.$article->getProperty('uri').'">'.$article->getProperty('title').'</a>';
	}
	public function formatTitle()
	{
		$title=$this->data->getProperty('title');
		
		$words=preg_split('/[\s]+/i', $title);
		
		$title='';
		$i=1;
		
		
		foreach($words as $word)
		{
			$title .= ($title ? ' ' : '').'<span class="h1-word-'.$i.'">'.$word.'</span>';
			$i++;
		}
		
		return $title;
	}
}

class ArticlesListView extends ArticleView
{	
	public function formatLink($article, $localLink=false)
	{
		if(!$localLink)
		{
			return '<a href="'.static::$___documentRootUrl.$article->getProperty('uri').'">'.$article->getProperty('title').'</a>';
		}
		else
		{
			return '<a href="#" name="'.$article->getAnchorName().'">'.$article->getProperty('title').'</a>';
		}
	}
}