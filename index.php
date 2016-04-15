<?php

// SEOMoz Access / Secret
define('SEOMOZ_ACCESS_ID','mozscape-ee0952a5bb');
define('SEOMOZ_SECRET_KEY','b7bddd042cca01820f9a9f95de1781a7');

if ( (function_exists('session_status') && session_status() == PHP_SESSION_NONE) || !isset($_SESSION)) {
    session_start();
}

// --------------- GENERATE A CSV --------------------
if(isset($_POST['export-urls-metrics']) && isset($_SESSION['urls_metrics']) && !empty($_SESSION['urls_metrics'])) {

// output headers so that the file is downloaded rather than displayed
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bulk-metric-export-'.time().'.csv');

    // create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // output the column headings
    fputcsv($output, array('URL','DA','PA','MR','EL'));

	// Generate data rows
	foreach ($_SESSION['urls_metrics'] as $url_metrics) {
        fputcsv($output, array(
            trim(str_replace("http://","",$url_metrics['url'])),
            $url_metrics['da'],
            $url_metrics['pa'],
            $url_metrics['mozrank'],
            $url_metrics['external_links']
        ));
    }
    exit;
}
// --------------- END OF CSV CODE --------------------


// Output a form to enter URLs
?>
<center>
<h1>Moz Metrics</h1>
<form method="post">
Enter URL Here:
<br />
<textarea name="url_form" cols="40" rows="8" style="width: 400px">
<?=$_REQUEST['url_form'];?>
</textarea >
<br />
<input type="submit" style="margin-top: 5px; font-size: 18px" value="Check Metrics" />
</form>
</center>

<?php

// Grab all URLs, clean and check them
if($_REQUEST['url_form']) {
$urls = trim($_POST['url_form']);
$urls = explode("\n", $urls);
$urls = array_filter($urls, 'trim');
}

// If there aren't any URLs, do nothing.
if(!$urls) {
	exit;
}

?>

<div style="margin: auto; width: 50%; min-width: 400px">
<table width="500" cellpadding="5" cellspacing="5">
    <thead style="text-align: left">
        <tr>
            <th>ID</th>
            <th>URL</th>
            <th>DA</th>
            <th>PA</th>
            <th>MR</th>
            <th>EL</th>
        </tr>
    </thead>
    <tbody>

<?php

$check_count = 0;
$urls_metrics = array();

// Split the array into chunks so that it can be checked with Moz.
$chunked_verified_urls = array_chunk($urls,80);

foreach($chunked_verified_urls as $chunk_section) {
	// Sleep for 2 seconds just to make sure we're not going over our rate limit
	sleep(2);
	unset($url);
	$url = $chunk_section;

	// Get SEOmoz Metrics
	$seomoz_metrics = getSeomozMetrics($url);

	// Check for SEOmoz Error
	if($seomoz_metrics['error'] != '') {
		echo "Error[SEOMoz]: ".$seomoz_metrics['error']."<br>";
	} else {
		foreach($seomoz_metrics as $index => $seomoz_metric) {
			// Add SEOmoz Metrics to array
			$url_metrics['pa'] = number_format($seomoz_metric['pa'], 0, '.', '');
			$url_metrics['url'] = $seomoz_metric['url'];
			$url_metrics['da'] = number_format($seomoz_metric['da'], 0, '.', '');
	//		$url_metrics['title'] = $seomoz_metric['title'];
			$url_metrics['external_links'] = $seomoz_metric['external_links'];
			$url_metrics['mozrank'] = number_format($seomoz_metric['mozrank'], 2, '.', '');
			$check_count++;

			echo "<tr><td>";
				echo $check_count;
			echo "</td><td>";
				echo str_replace("http://","",$url_metrics['url']);
			echo "</td><td>";
				echo $url_metrics['da'];
			echo "</td><td>";
				echo $url_metrics['pa'];
			echo "</td><td>";
				echo $url_metrics['mozrank'];
			echo "</td><td>";
				echo $url_metrics['external_links'];
			echo "</td>";
			echo "</tr>";

			$urls_metrics[] = $url_metrics;
        }
	}
}
?>

</tbody></table>

<br><br>
<center><small><em>DA = Domain Authority, PA = Page Authority, MR = MozRank, EL = External Links to URL</em></small>
<br><br>

<?php
$_SESSION['urls_metrics'] = $urls_metrics;
    if(!empty($urls_metrics)) {
?>
<form method="post">
    <button type="submit" class="btn btn-primary" name="export-urls-metrics" >Export to CSV</button>
</form>
<?php
    }
?>

</center>
</div>

<?php

function getSeomozMetrics($objectURL) {

		//Add your accessID here
		$accessID = SEOMOZ_ACCESS_ID;
		//Add your secretKey here
		$secretKey = SEOMOZ_SECRET_KEY;

		// Set the expiry time for the call.
		$expires = time() + 600;

		// A new linefeed is necessary between your AccessID and Expires.
		$stringToSign = $accessID."\n".$expires;

		// Get the "raw" or binary output of the hmac hash.
		$binarySignature = hash_hmac('sha1', $stringToSign, $secretKey, true);

		// We need to base64-encode it and then url-encode that.
		$urlSafeSignature = urlencode(base64_encode($binarySignature));

		// Add up all the bit flags you want returned.
		// Learn more here: http://apiwiki.seomoz.org/categories/api-reference
		$cols = 68719476736+34359738368+536870912+32768+16384+2048+32+4;

		// Put it all together and you get your request URL.
		$requestUrl = "http://lsapi.seomoz.com/linkscape/url-metrics/?Cols=".$cols."&AccessID=".$accessID."&Expires=".$expires."&Signature=".$urlSafeSignature;

		// Put your URLS into an array and json_encode them.
		$batchedDomains = $objectURL;
		$encodedDomains = json_encode($batchedDomains);

		// We can easily use Curl to send off our request.
		// Note that we send our encoded list of domains through curl's POSTFIELDS.
		$options = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS     => $encodedDomains
			);

		$ch = curl_init($requestUrl);
		curl_setopt_array($ch, $options);
		$content = curl_exec($ch);
		curl_close( $ch );

		$response = json_decode($content,true);

		$count = 0;

    if (isset($response['error_message'])) {
        $metric_list = array('error'=>$response['error_message']);
    } else {
		// For each URL add the metrics
        foreach($response as $site_metric) {
			// Translate the Moz API info into something a bit more understandable
			$metric_list[$count]['url'] = $objectURL[$count];
			//$metric_list[$count]['subdomain'] = $site_metric['ufq'];
        //   $metric_list[$count]['domain'] = $site_metric['upl'];
           $metric_list[$count]['pa'] = $site_metric['upa'];
           $metric_list[$count]['da'] = $site_metric['pda'];
           $metric_list[$count]['mozrank'] = $site_metric['umrp'];
        //   $metric_list[$count]['title'] = $site_metric['ut'];
           $metric_list[$count]['external_links'] = $site_metric['ueid'];
			$count++;
        }
    }
	// Send back the data
	return $metric_list;
}

?>
