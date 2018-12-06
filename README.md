# iformbuilder-pdf-generator
A simple utility designed to loop through a number of forms, generating a PDF and storing it locally. You need to add one file to the **auth** folder (keys.php).

The keys.php file should look like the example below. The server name, client key and secret needs to be associated to a user which has read access for the list of forms.

You can use the `$fieldGrammar` parameter to supply a filter for the PDFs that you want. The filter will be reused for all the forms defined in the page array. Make sure to URLEncode the filter you pass in.

```php
<?php
//::::::::::::::  SET STATIC VARIABLES   ::::::::::::::
$server = '####'; // 'apple.iformbuilder.com' or 'apple.zerionsandbox.com'
$client = '####'; // 'abc123'
$secret = '####'; // 'abc123'
$profileId = '####'; // '123456'
$pageArray = ["####","####"]; // '123456'
$fieldGrammar = 'fields=id'; // 'fields=country(=%22USA%22)'
$username = '####'; // 'testuser'
$password = '####'; // 'testpassword'
?>
```

Check out the getting started video below for a crash course on getting setup.

https://youtu.be/vZwbytNYiDk

When the utility finishes you will see a summary count of all the files that were created along with the total amount of time saved. The example below took just over 12 hours and downloaded 27,000 different records. 

![Summary view after script completion](https://user-images.githubusercontent.com/7986768/49557947-599e3e00-f8d7-11e8-9fda-89f8d44e9353.png)

