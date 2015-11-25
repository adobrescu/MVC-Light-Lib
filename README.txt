MVC library


It contains MVC classes:

- View and controller classes are designed to allow an application layout very close to a classic PHP application based on "include"s;
- Model classes includes, besides standard record classes that allow basic work with databases, usefull classes that implements 
some data tree structures. Suggestive examples:

//1. display the uri for all images linked to all published articles of a user
$user=new User($idUser = 1 );

foreach($user->articles->wherePublished(true)->images->items() as $image)
{
	echo $image->getSrc();
}


//2. build a complex form for adding an article 
//(with linked records from more than one table)
$userData=new UserFormData(1);

$userData->articles->addNew();
$userData->articles->items(0)->images->addNew();

...

//show form inputs
...
$userData->articles->items(0)->title->showInput();

$userData->articles->items(0)->images->items(0)->title->showInput();



// ON SUNMIT

try
{
	$userData->import($_POST);
	$userData->saveAll(); //this saves the images and the article
}
catch(FormException $err)
{
	//display errors ... 
}



