# iformbuilder-field-creator
A simple utility designed to loop through a number of forms, generating a PDF and storing it locally.

You also need to add one file to the **auth** folder (keys.php). The keys.php file should look like the example below. The server name, client key and secret needs to be associated to a user which has read access for the list of forms.

You can use the $fieldGrammar parameter to supply a filter for the PDFs that you want. The filter will be reused for all the forms defined in the page array.

```php
<?php
//::::::::::::::  SET STATIC VARIABLES   ::::::::::::::
$server = '####'; //apple
$client = '####'; //abc123
$secret = '####'; //abc123
$profileId = '####'; //123456
$pageArray = ["####","####"];
$fieldGrammar = 'fields=id';
$username = '####'; //
$password = '####'; //
?>
