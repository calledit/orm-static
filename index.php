<?php
ini_set('display_errors', 1);

$DatabaseName = 'PickPack';
/*Connect to the database*/
$MY_SQL_Handle = new mysqli($SQL_host, $SQL_user, $SQL_pass, $mysql_db_name);
if(!$MY_SQL_Handle){
	throw new Exception('MY SQL Database connection failed');
}
$MY_SQL_Handle->set_charset('utf8');


//make sure we dont have to care about ONLY_FULL_GROUP_BY
$sql_mode = simple_DB_query('SELECT @@sql_mode');
$modes = explode(',', $sql_mode[0]['.@@sql_mode']);

foreach($modes AS $mdid => $mode){
	if($mode == 'ONLY_FULL_GROUP_BY'){
		unset($modes[$mdid]);
	}
}
$simple_MY_SQL_Handle->query('set session sql_mode=\''.$simple_MY_SQL_Handle->real_escape_string(implode(',', $modes)).'\'');

//Import object descriptors
require_once("db_classes.php");

//Create static object description
SaveDBClassesToFile($DatabaseName, 'generated_db_classes.php', $MY_SQL_Handle);

//Import the generated classes
require_once("generated_db_classes.php");


//Simple Example usage query
$DeliverysToPartners = DB_query(
	'SELECT `deliveries`.*, `bundle_invoices`.*, `delivery_partners`.* FROM `delivery_partners`
		INNER JOIN `deliveries` ON `deliveries`.`_id` = `delivery_partners`.`Delivery`
		LEFT OUTER JOIN `delivery_invoices` ON `deliveries`.`_id` = `delivery_invoices`.`Delivery`
		LEFT OUTER JOIN `bundle_invoices` ON `delivery_invoices`.`InvoiceID` = `bundle_invoices`.`_id`
	WHERE
		`deliveries`.`Season` = '.DB_Esc(201401).' AND
		`delivery_partners`.`Partner` = '.DB_Esc(8)
		);

//Foreach partner that is to have a delivery
foreach($DeliverysToPartners['delivery_partners'] AS $Partner){
	
	//get The delivery that the partner was connected to
	$WhichDelivery = $Partner->RefRow('deliveries', $DeliverysToPartners);
	//get the Invoices the delivery was connected to 
	$WhichInvoices = $WhichDelivery->RefRow('bundle_invoices', $DeliverysToPartners, true);

	echo("<div>");
	echo("Partner: ".$Partner->Partner."\n");
	echo("Has folowing invoices:\n");
	
	foreach($WhichInvoices AS $Invoice){
		echo("Invoice: ".$Invoice->Name."\n");
	}
	echo("</div>");
}

