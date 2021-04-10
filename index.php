<?php
ini_set('display_errors', 1);

$DatabaseName = 'words';
$SQL_user = 'words';
$SQL_pass = '8lj9R1C1IE711nWN';
$SQL_host = 'localhost';
/*Connect to the database*/
$MY_SQL_Handle = new mysqli($SQL_host, $SQL_user, $SQL_pass, $DatabaseName);
if(!$MY_SQL_Handle){
	throw new Exception('MY SQL Database connection failed');
}
$MY_SQL_Handle->set_charset('utf8');

//Import object descriptors
require_once("db_classes.php");
require_once("query_view.php");

//make sure we dont have to care about ONLY_FULL_GROUP_BY
$sql_mode = simple_DB_query('SELECT @@sql_mode');
$modes = explode(',', $sql_mode[0]['.@@sql_mode']);

foreach($modes AS $mdid => $mode){
	if($mode == 'ONLY_FULL_GROUP_BY'){
		unset($modes[$mdid]);
	}
}
$MY_SQL_Handle->query('set session sql_mode=\''.$MY_SQL_Handle->real_escape_string(implode(',', $modes)).'\'');

$orm_static = false;
if(!$orm_static){
	
	//query view uses sessions for chaching
	session_start();

	$wordviewer = new QUERYview('?a=1&b=2', 
'SELECT * FROM `dictionary_word`
LEFT OUTER JOIN `dictionary_word_translation` ON `dictionary_word`.`_id` = `dictionary_word_translation`.`dictionary_word_id`
LEFT OUTER JOIN `dictionary_word_synonym` ON `dictionary_word`.`_id` = `dictionary_word_synonym`.`dictionary_word_id`
'
);

//Alter the $wordviewer

$wordviewer->handle_exec();

?>
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>
<script src="query_view.js"></script>
<?php


$wordviewer->render();

}else{

//Create static object description
if(!file_exists('generated_db_classes.php')){
	SaveDBClassesToFile($DatabaseName, 'generated_db_classes.php', $MY_SQL_Handle);
}

//Import the generated classes
require_once("generated_db_classes.php");


//Simple Example usage query
$Words_translations_and_synonyms = DB_query(
'SELECT * FROM `dictionary_word`
LEFT OUTER JOIN `dictionary_word_translation` ON `dictionary_word`.`_id` = `dictionary_word_translation`.`dictionary_word_id`
LEFT OUTER JOIN `dictionary_word_synonym` ON `dictionary_word`.`_id` = `dictionary_word_synonym`.`dictionary_word_id`
LIMIT 1000'
);

//Foreach partner that is to have a delivery
foreach($Words_translations_and_synonyms['dictionary_word'] AS $Word){
	
	//get The delivery that the partner was connected to
	$AllSynonyms = $Word->RefRow('dictionary_word_synonym', $Words_translations_and_synonyms, true);
	//get the Invoices the delivery was connected to 
	$AllTranslations = $Word->RefRow('dictionary_word_translation', $Words_translations_and_synonyms, true);

	echo("<div style=\"border: 1px solid black;\">\n");
	echo("<h3>". $Word->value."</h3>\n");
	//var_dump($Word);
	echo("<p>\n");
	echo("Synonyms:\n");
	foreach($AllSynonyms AS $synonym){
		echo($synonym->value.",\n");
	}
	echo("</p>\n");
	//echo("Partner: ".$Partner->Partner."\n");
	echo("<p>\n");
	echo("\nTranslations:\n");
	
	foreach($AllTranslations AS $translation){
		echo($translation->value.",\n");
	}
	echo("</p>\n");
	echo("</div>\n\n");
}

}
