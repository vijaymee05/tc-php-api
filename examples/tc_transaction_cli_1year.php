<?php
use Google\Client;
use Google\Service\Drive;
use Google\Service\Sheets\ValueRange;
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;
include '../bootstrap.php';

// These parameters are just for the example site
$example_name = 'Transaction Search';
//include 'inc.header.php';
set_time_limit(0);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('default_socket_timeout', '100000');
error_reporting(E_ALL);

$access_token = 'QVBYWKME-6X0IW8KI-Y85V222X-48QO0M16';

// Initialise our API object
$tc = new \ThriveCart\Api($access_token);

// How many results per page?
$perPage = 100; // @note Maximum of 25 results per page

// What page are we viewing?
$page = 1; // @note 1 through N
$countRecordMatch = 1;

$currentDate = date("Y-m-d");
$firstQuarterRange = "2022-04-01";
$firstQuarter = date("Y-m-d",strtotime($firstQuarterRange."-3 month"));
$secondQuarterRange = "2022-07-01";
$secondQuarter = date("Y-m-d",strtotime($secondQuarterRange."-3 month"));
$thirdQuarterRange = "2022-10-01";
$thirdQuarter = date("Y-m-d",strtotime($thirdQuarterRange."-3 month"));
$fourthQuarterRange = "2023-01-01";
$fourthQuarter = date("Y-m-d",strtotime($fourthQuarterRange."-3 month"));
//echo "1st: ".$firstQuarter." 2nd: ".$secondQuarter." 3rd: ".$thirdQuarter." 4th: ".$fourthQuarter;exit;
switch ($argv[1]) {
	case "1q":
		$quarterRange = "2022-04-01";
		$timePeriod = date("Y-m-d",strtotime($quarterRange."-3 month"));
		$timePeriodLabel = "First Quarter";
		$sheetName = "TC-Year-1Qaurter";
		break;
	case "2q":
		$quarterRange = "2022-07-01";
		$timePeriod = date("Y-m-d",strtotime($quarterRange."-3 month"));
		$timePeriodLabel = "Second Quarter";
		$sheetName = "TC-Year-2Qaurter";
		break;
	case "3q":
		$quarterRange = "2022-10-01";
		$timePeriod = date("Y-m-d",strtotime($quarterRange."-3 month"));
		$timePeriodLabel = "Third Quarter";
		$sheetName = "TC-Year-3Qaurter";
		break;
	default:
		$quarterRange = "2023-01-01";
		$timePeriod = date("Y-m-d",strtotime($quarterRange."-3 month"));
		$timePeriodLabel = "Final Quarter";
		$sheetName = "TC-Year-4Qaurter";
		break;
}

// Now let's make our API request
try {
	echo "\nStep-1: Calling transaction API to total number of pages.\n";
	$response = $tc->transactions(array(
		// Filters
		//'query' => $query,
		'transactionType' => 'charge', // @note This can be 'any', 'charge', 'refund', 'rebill', 'cancel' - the returned list of transactions will include a transaction_type that has one of these values also
		// Pagination
		'page' => $page,
		'perPage' => $perPage,
	));
	// Are there more pages of results?
	if(!empty($response['meta']['total']) && $response['meta']['total'] >= $perPage) {
		$total_pages = ceil($response['meta']['total'] / $perPage);
		$remaining_pages = $total_pages - $page;
	}
	$lead_sources =array();
	$dateMatched = false;
	//Collect the all the date matched data into single array first
	//for loop START
	echo "\nTotal Pages Found: ".$total_pages." and today's date is: ".$currentDate."\n";
	echo "\nStep-2: Getting data for each transaction.\n";
	for($i = $page; $i <= $total_pages; $i++){
		$final_response = $tc->transactions(array(
			'transactionType' => 'charge', 
			'page' => $i,
			'perPage' => $perPage,
		));
		if($dateMatched === false){
			foreach ($final_response['transactions'] as $key=>$value) {
				echo "\nStep-3: Checking data for ".$timePeriodLabel." i.e.(". $timePeriod.").\n";
				if($value['date'] >= $timePeriod && $value['date'] < $quarterRange ){
					echo " \n\t\t Customer email is ".$value['customer']['email']." and transaction date is: ".$value['date']."\n";
					$countRecordMatch ++;
					$customer = $tc->customer(array(
						'email' => $value['customer']['email'],
					));
					echo "\nStep-4: Pulled the customer data for respective transaction.\n";
					$lead_name = 'null';
					if(!empty($customer['customer']['custom_fields']) && $customer['customer']['custom_fields'] != "" )
					{
						$lead_name = $customer['customer']['custom_fields']['leadsource'];
					}
					$lead_sources[] = array('item_name'=>$lead_name,'name'=> $value['customer']['name'],'email'=> $value['customer']['email'], 'amount'=> floatval($value['amount']/100));
					sleep(4);
				}
				elseif($value['date'] <= $timePeriod){
					$dateMatched = true;
					break;
				}
			}
		}
	}
	echo "\nTotal Matched Record: ".$countRecordMatch."\n";
	echo "\nStep-5: User data manipulation is completed.\n";
	//for loop END
	//Update the above data into single entity for each user i.e. addition of amount for each user respectively.
	echo "\nStep-6: Lead Source data manipulation is started.\n";
	$users =array();
	foreach ($lead_sources as $lead_source) {
		$amount = 0;
		if(!in_array($lead_source['email'], $users)){
			$users[] = $lead_source['email'];
			$updatedUsers[$lead_source['email']] = [$lead_source['name'], $lead_source['email'], floatval($lead_source['amount']), $lead_source['item_name']];
			$feedUsersSheet[] = [$lead_source['name'], $lead_source['email'], floatval($lead_source['amount']), $lead_source['item_name']];
		}
		else{ 
			$vjarr = $updatedUsers[$lead_source['email']];
			$amount =$vjarr[2];
			$updatedUsers[$lead_source['email']] = [$lead_source['name'], $lead_source['email'], (floatval($lead_source['amount'])+ floatval($amount)), $lead_source['item_name']];
			$feedUsersSheet[] = [$lead_source['name'], $lead_source['email'], (floatval($lead_source['amount'])+ floatval($amount)), $lead_source['item_name']];
		}
	}
	
	/******* this code is written for dataset 2 ***** */
	$lead_sources =array();
	$count=0;
	
	foreach ($updatedUsers as $updatedUser) {
		if(!in_array($updatedUser[3], $lead_sources)){
			$userCount = 1;
			$lead_sources[$count] = $updatedUser[3];
			$updatedLeads[$updatedUser[3]] = [$updatedUser[3], floatval($updatedUser[2]), $userCount];
			$feedLeads[] = [$updatedUser[3], floatval($updatedUser[2]), $userCount];
			$count++;
		}
		else{
			$vjarr = $updatedLeads[$updatedUser[3]];
			$amount =$vjarr[1];
			$newCount = $vjarr[2];
			$updatedLeads[$updatedUser[3]] = [$updatedUser[3], (floatval($updatedUser[2])+ floatval($amount)), ($newCount+$userCount)];
			//$feedLeads[] = [$updatedUser[3], (floatval($updatedUser[2])+ floatval($amount)), ($newCount+$userCount)];
		}
	}
	echo "\nStep-7: Lead Source data manipulation is completed.\n";
	echo "\nStep-8: User data and lead source data started to feed into google sheet.\n";
	
	$updatedUsers = array_values($updatedUsers);
	$client = getClient();
	$service = new Google\Service\Sheets($client);
	$spreadsheetId = '1i8TI4HUdRmUwNdrw6hVP_ujzk4xPdDqiDmY9jcJmikM';
	
	$valueInputOption = 'RAW';
	//Feed user data into google sheet
	$range = $sheetName.'!A3:D';
	$body = new Google_Service_Sheets_ValueRange([
		'values' => $updatedUsers
	]);
	$params = [
		'valueInputOption' => $valueInputOption
	];
	//Clear user's content of the sheet
	$requestBody = new Google_Service_Sheets_ClearValuesRequest();
	$clearUserResponse = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

	//executing the request
	$result = $service->spreadsheets_values->update($spreadsheetId, $range,
	$body, $params);
	$result->getUpdatedCells();
	
	//Feed lead data into google sheet
	$updatedLeads = array_values($updatedLeads);
	$range = $sheetName.'!F4:H';
	$body = new Google_Service_Sheets_ValueRange([
		'values' => $updatedLeads
	]);

	//Clear lead's content of the sheet
	$clearLeadResponse = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);
	$result = $service->spreadsheets_values->update($spreadsheetId, $range,
	$body, $params);
	$result->getUpdatedCells();
	echo "\nStep-9: User data  and lead source feeded into google sheet.\n";
	printf("<h1>Data sheet is updated.");
	

} catch(\ThriveCart\Exception $e) {
	echo '<div class="notification is-danger is-light">There was an error while searching through your transactions: '.$e->getMessage().'</div>';
}

function getClient()
{
    $client = new Google\Client();
    $client->setApplicationName('Groweverywhere');
    $client->setScopes('https://www.googleapis.com/auth/spreadsheets');
    $client->setAuthConfig('../credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = '../token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}
?>