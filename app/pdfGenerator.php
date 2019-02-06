<?php

header('Access-Control-Allow-Methods: POST, PUT');
header("Access-Control-Allow-Headers: X-Requested-With");

require_once "../auth/iFormTokenResolver.php";
require_once "../auth/keys2.php";
use iForm\Auth\iFormTokenResolver;

//:::::::::::::: Define the environment where we obtain an access token ::::::::::::::

$tokenUrl = 'https://' . $server . '/exzact/api/oauth/token';
$time_start = microtime(true);

//:::::::::::::: Need to get the name of the active page so we can use it later in the PDF request and to create the directories. ::::::::::::::

foreach($pageArray as $activePage) {

  // We need to get all of the details for the page (ID, Name of PDF File, Filter Grammar)
  $pageDetails = explode(';',$activePage);
  $activePageId = $pageDetails[0];
  $activePageFileName = $pageDetails[1];
  $activePageFieldGrammar = $pageDetails[2];

  if ($activePageFileName==null) {
    $activePageFileName = 'id';
  }
  if ($activePageFieldGrammar==null) {
    $activePageFieldGrammar = 'fields=id';
  }

// Set a count so we can track how many records get created for each form
$currentFormRecordCount = 0;

//::::::::::::::  FETCH ACCESS TOKEN   ::::::::::::::
// Couldn't wrap method call in PHP 5.3 so this has to become two separate variables
$tokenFetcher = new iFormTokenResolver($tokenUrl, $client, $secret);
$token = $tokenFetcher->getToken();
echo $token;

echo "Active Page ID: ".$activePageId."\r\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://" . $server . "/exzact/api/v60/profiles/$profileId/pages/$activePageId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_HEADER, FALSE);

curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  "Authorization: Bearer $token"
  ));

  $response = curl_exec($ch);
  if(curl_errno($ch))
      echo 'Curl error: '.curl_error($ch);
  curl_close($ch);

  //:::::::::::::: Create a new directory using the Form Label as the folder name.
  $activePageJson = json_decode($response,true);
  $activePageLabel = $activePageJson["label"];
  $activePageName = $activePageJson["name"];
  echo "Active Page Label: ".$activePageLabel."\r\n";
  mkdir("../$activePageName");


  //:::::::::::::: Send one request to determine total number of records in the response header (Total-Count) ::::::::::::::
  $recordListUrl = "https://" . $server . "/exzact/api/v60/profiles/$profileId/pages/$activePageId/records?$activePageFieldGrammar&limit=1&access_token=" . $token;

  // Parse the response headers and figure out how many records we need to process
  $recordRequestHeaders = (get_headers($recordListUrl,1));
  $finalRecordCount = $recordRequestHeaders["Total-Count"];

  echo("Number of Records:" . $finalRecordCount . "\r\n");

  // Determine how many times we have to iterate through the record list if over 1000
  $recordLimit=1000;
  $timesToRun = $finalRecordCount/$recordLimit;
  $offset = 0;
  echo "Number of loops required: " . $timesToRun . "\r\n";

  for ($i=0;$i<$timesToRun;$i++)  {

    // Get a fresh access token because it can expire if there are thousands of PDFs to generate
    $tokenFetcher = new iFormTokenResolver($tokenUrl, $client, $secret);
    $token = $tokenFetcher->getToken();

    //:::::::::::::: Fetch the most recent list of records for the active form ::::::::::::::
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://" . $server . "/exzact/api/v60/profiles/$profileId/pages/$activePageId/records?$activePageFieldGrammar&offset=$offset&limit=$recordLimit");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      "Authorization: Bearer $token"
      ));

      $response = curl_exec($ch);
      print_r($response . "\r\n");
      if(curl_errno($ch))
          echo 'Curl error: '.curl_error($ch);
      curl_close($ch);

      // Get the JSON response into an array so we can loop through it.
      $activeRecordJson = json_decode($response,true);

      // Track the total number of PDFs we create accross all forms.
      $totalRecordCount = $totalRecordCount+(sizeof($activeRecordJson));

      // For each record we need to call the iFormBuilder PDF resource and pass in the relevant parameters
      foreach($activeRecordJson as $activeRecord) {

        $activeRecordId = $activeRecord['id'];
        $activeRecordName = $activeRecord[$activePageFileName];
        print_r("Downloading Record: " . $activeRecordName . "\r\n");

      // Increment the record count
      $currentFormRecordCount = $currentFormRecordCount+1;

      // Make the request for a PDF here.
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, "https://" . $server . "/exzact/dataPDF.php?TABLE_NAME=_data$profileId$activePageName&ID=$activeRecordId&PAGE_ID=$activePageId&USERNAME=$username&PASSWORD=$password");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, FALSE);

        $response = curl_exec($ch);
        if(curl_errno($ch))
            echo 'Curl error: '.curl_error($ch);
        curl_close($ch);

        // Save the PDF that we just requested in the proper directory 
        if ($activePageFileName!='id') {
        file_put_contents ("../$activePageName/$activeRecordName($activeRecordId).pdf" ,$response);
      } else
        file_put_contents ("../$activePageName/$activeRecordName.pdf" ,$response);
      }

      // Add to the offset to keep working through the records not yet processed
      $offset=($i+1)*$recordLimit;
      echo "Number of records completed for current form: " . $currentFormRecordCount . "\r\n\r\n";
    }
}
$time_end = microtime(true);
//dividing with 60 will give the execution time in minutes otherwise seconds
$execution_time = ($time_end - $time_start)/60;

echo "Total number of records downloaded accross all forms: " . $totalRecordCount . "\r\n";
echo "This tool just saved about " . round($execution_time) . " minutes\r\n";
?>
