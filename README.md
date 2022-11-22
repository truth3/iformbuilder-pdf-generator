# iformbuilder-pdf-generator
A simple utility designed to loop through a number of forms, generating a PDF and storing it locally. You need to add one file to the **auth** folder (keys.php).

The keys.php file should look like the example below. The server name, client key and secret needs to be associated to a user which has read access for the list of forms.

The `$pageArray` variable contains four values separated by a semi-colon.

The first value should be the **page_id** of the form you want to create PDFs for.

The second value should be the **data column name(s)** within the form you want to use for the title of the PDF. The value(s) in the defined column(s) will be used to name the PDF for the given record. If you need to use multiple fields to create the name you will use the structure below.

`column1-column2-column3`

The third value should be the **separator** that you want to use between each value in the file name. If using a single column name, or the default of record id, then don't include a value. If using hyphen add a hyphen, if using a space, include a space. No special encoding required.

The fourth value should be valid field grammar which will be used to filter the records for the given **page_id**. Make sure to URLEncode the filter you pass in. You must also include fields that you want to use for the name of the PDF even if they don't have any filters applied.

```php
<?php
//::::::::::::::  SET STATIC VARIABLES   ::::::::::::::
$server = '####'; // 'apple.iformbuilder.com' or 'apple.zerionsandbox.com'
$client = '####'; // 'abc123'
$secret = '####'; // 'abc123'
$profileId = '####'; // '123456'
$pageArray = ["####;####;####;####","####;####;####;####"]; // '123456;inspector_name-company_name; ;fields=id,company_name,inspector_name'
$username = '####'; // 'testuser'
$password = '####'; // 'testpassword'
?>
```

Check out the getting started video below for a crash course on getting setup.

https://youtu.be/vZwbytNYiDk

When the utility finishes you will see a summary count of all the files that were created along with the total amount of time saved. The example below took just over 12 hours and downloaded 27,000 different records.

![Summary view after script completion](https://user-images.githubusercontent.com/7986768/49557947-599e3e00-f8d7-11e8-9fda-89f8d44e9353.png)
