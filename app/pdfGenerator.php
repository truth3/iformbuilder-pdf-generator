<?php
declare(strict_types=1);

header('Access-Control-Allow-Methods: POST, PUT');
header("Access-Control-Allow-Headers: X-Requested-With");

require_once "../auth/iFormTokenResolver.php";
require_once "../auth/keys.php";
use iForm\Auth\iFormTokenResolver;

//:::::::::::::: Define the environment where we obtain an access token ::::::::::::::

$tokenUrl = 'https://' . $server . '/exzact/api/oauth/token';
$time_start = microtime(true);
$totalRecordCount = 0;

//:::::::::::::: Need to get the name of the active page so we can use it later in the PDF request and to create the directories. ::::::::::::::

foreach($pageArray as $activePage) {
  try {
    // We need to get separate all of the details for the page (ID, Name of PDF File, Separator for Name, Filter Grammar)
    $pageDetails = explode(';', $activePage);
    $activePageId = $pageDetails[0] ?? null; 
    $activePageFileName = $pageDetails[1] ?? 'id';
    $activePageFileNameSeparator = $pageDetails[2] ?? '';
    $activePageFieldGrammar = $pageDetails[3] ?? 'fields=id';

    // Set a count so we can track how many records get created for each form
    $currentFormRecordCount = 0;

    //::::::::::::::  FETCH ACCESS TOKEN   ::::::::::::::
    $tokenFetcher = new iFormTokenResolver($tokenUrl, $client, $secret);
    $token = $tokenFetcher->getToken();

    // Do some checks on the access credentials and give feedback to the user if something is wrong
    if (str_contains($token, 'invalid_client')) {
      echo "Invalid Client Key in keys.php. \r\n";
      continue;
    } else if (str_contains($token, 'invalid_grant')) {
      echo "Invalid Secret in keys.php or machine time is not accurate. \r\n";
      continue;
    }

    //::::::::::::::  CHECK USERNAME AND PASSWORD / USER LOCKED STATUS BEFORE WE GO ANY FURTHER   ::::::::::::::
    $ch = curl_init();
    if ($ch === false) {
      throw new \RuntimeException('Failed to initialize cURL');
    }
    
    curl_setopt($ch, CURLOPT_URL, "https://" . $server . "/exzact/dataViews.php?USERNAME=$username&PASSWORD=$password");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);

    $response = curl_exec($ch);
    if ($response === false) {
      throw new \RuntimeException('cURL error: ' . curl_error($ch));
    }

    if (str_contains($response, "Account is locked")) {
      echo "User account is locked, please unlock from the admin portal and try again. \r\n";
      curl_close($ch);
      continue;
    } else if (str_contains($response, "Invalid username")) {
      echo "Invalid username or password supplied in the config file. Please correct, and try again. \r\n";
      curl_close($ch);
      continue;
    }
    curl_close($ch);

    echo "Active Page ID: " . $activePageId . "\r\n";
    $ch = curl_init();
    if ($ch === false) {
      throw new \RuntimeException('Failed to initialize cURL');
    }
    
    curl_setopt($ch, CURLOPT_URL, "https://" . $server . "/exzact/api/v60/profiles/$profileId/pages/$activePageId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    
    $response = curl_exec($ch);
    if ($response === false) {
      echo 'Curl error: ' . curl_error($ch);
    }
    curl_close($ch);

    //:::::::::::::: Create a new directory using the Form Label as the folder name.
    $activePageJson = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    $activePageLabel = $activePageJson["label"] ?? '';
    $activePageName = $activePageJson["name"] ?? '';
    
    echo "Active Page Label: " . $activePageLabel . "\r\n";
    if (!file_exists("../$activePageName")) {
      mkdir("../$activePageName", 0777, true);
    }

    //:::::::::::::: Send one request to determine total number of records in the response header (Total-Count) ::::::::::::::
    $recordListUrl = "https://" . $server . "/exzact/api/v60/profiles/$profileId/pages/$activePageId/records?$activePageFieldGrammar&limit=1&access_token=" . $token;

    // Parse the response headers and figure out how many records we need to process
    $recordRequestHeaders = get_headers($recordListUrl, 1);
    if ($recordRequestHeaders === false) {
      throw new \RuntimeException('Failed to get headers from ' . $recordListUrl);
    }
    
    $finalRecordCount = (int)($recordRequestHeaders["Total-Count"] ?? 0);

    echo("Number of Records:" . $finalRecordCount . "\r\n");

    // Determine how many times we have to iterate through the record list if over 1000
    $recordLimit = 1000;
    $timesToRun = ceil($finalRecordCount / $recordLimit);
    $offset = 0;
    echo "Number of loops required: " . $timesToRun . "\r\n";

    for ($i = 0; $i < $timesToRun; $i++) {
      // Get a fresh access token because it can expire if there are thousands of PDFs to generate
      $tokenFetcher = new iFormTokenResolver($tokenUrl, $client, $secret);
      $token = $tokenFetcher->getToken();

      //:::::::::::::: Fetch the most recent list of records for the active form ::::::::::::::
      $ch = curl_init();
      if ($ch === false) {
        throw new \RuntimeException('Failed to initialize cURL');
      }
      
      curl_setopt($ch, CURLOPT_URL, "https://" . $server . "/exzact/api/v60/profiles/$profileId/pages/$activePageId/records?$activePageFieldGrammar&offset=$offset&limit=$recordLimit");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);

      $response = curl_exec($ch);
      
      if ($response === false) {
        echo 'Curl error: ' . curl_error($ch);
        curl_close($ch);
        continue;
      }
      curl_close($ch);

      // Get the JSON response into an array so we can loop through it.
      $activeRecordJson = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

      // Track the total number of PDFs we create across all forms.
      $recordsInBatch = count($activeRecordJson);
      $totalRecordCount += $recordsInBatch;

      // For each record we need to call the iFormBuilder PDF resource and pass in the relevant parameters
      foreach ($activeRecordJson as $activeRecord) {
        $activeRecordId = $activeRecord['id'] ?? '';
        $activeRecordName = '';  // Reset for each record

        // We need to handle multiple columns being used for the record name, so explode the list.
        $activePageFileNameDetails = explode('-', $activePageFileName);

        foreach ($activePageFileNameDetails as $fileNameField) {
          // We need to clean up special characters in the data being returned to make it safe for naming a file.
          if (isset($activeRecord[$fileNameField])) {
            $activeRecordName .= preg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '-', (string)$activeRecord[$fileNameField]) . $activePageFileNameSeparator;
          }
        }

        // Trim any trailing separators
        $activeRecordName = rtrim($activeRecordName, $activePageFileNameSeparator);

        $accessCheckUrl = "https://" . $server . "/exzact/dataPDF.php?TABLE_NAME=_data$profileId$activePageName&ID=$activeRecordId&PAGE_ID=$activePageId&USERNAME=$username&PASSWORD=$password";

        // Make the request for a PDF here.
        $ch = curl_init();
        if ($ch === false) {
          throw new \RuntimeException('Failed to initialize cURL');
        }
        
        curl_setopt($ch, CURLOPT_URL, $accessCheckUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        // Parse the response headers and figure out how many records we need to process
        $accessCheckRequestHeaders = get_headers($accessCheckUrl, 1);
        if ($accessCheckRequestHeaders === false) {
          echo("Failed to get headers from $accessCheckUrl \r\n");
          continue;
        }
        
        $responseType = $accessCheckRequestHeaders["Content-Type"] ?? '';

        if ($responseType != 'application/pdf') {
          echo("The user does not have access to this form, skipping to the next page \r\n");
          $totalRecordCount -= $recordsInBatch;
          break;
        }
        
        echo("Downloading Record: " . $activeRecordName . "\r\n");

        // Increment the record count
        $currentFormRecordCount++;

        $response = curl_exec($ch);
        
        if ($response === false) {
          echo 'Curl error: ' . curl_error($ch);
          curl_close($ch);
          continue;
        }
        curl_close($ch);

        // Save the PDF that we just requested in the proper directory
        $filePath = $activePageFileName != 'id' 
          ? "../$activePageName/$activeRecordName($activeRecordId).pdf"
          : "../$activePageName/$activeRecordName.pdf";
          
        file_put_contents($filePath, $response);
      }

      // Add to the offset to keep working through the records not yet processed
      $offset = ($i + 1) * $recordLimit;
      echo "Number of records completed for current form: " . $currentFormRecordCount . "\r\n\r\n";
    }
  } catch (\Exception $e) {
    echo "Error processing page: " . $e->getMessage() . "\r\n";
    continue;
  }
}

$time_end = microtime(true);
//dividing with 60 will give the execution time in minutes otherwise seconds
$execution_time = ($time_end - $time_start) / 60;

echo "Total number of records downloaded across all forms: " . $totalRecordCount . "\r\n";
echo "This tool just saved about " . round($execution_time) . " minutes\r\n\r\n";
?>
