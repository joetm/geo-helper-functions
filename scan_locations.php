<?php

error_reporting(E_ALL | E_NOTICE | E_STRICT);
//error_reporting(0);

?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <title>Geocode Locations</title>
   <meta http-equiv="content-type" content="text/html;charset=utf-8" />
</head>
<body>
<?php

define('__PATH', realpath(dirname(__FILE__)));

/*connect to database*/
require_once __PATH . "/db_connect.php";


function output_progress($msg)
{
		echo str_pad($msg . "<br />", 1024, ' ', STR_PAD_RIGHT) . PHP_EOL;

		while (ob_get_level()) {
			@ob_end_flush();
			@ob_flush();
			@flush();
		}

		@ob_start();
} //output_progress


$remaining = '';
$sql = "SELECT COUNT(wposts.ID) AS cnt

		FROM `".DBPREFIX."posts` AS wposts
		JOIN `".DBPREFIX."postmeta` AS loc1 ON (wposts.ID = loc1.post_id AND loc1.meta_key = 'z_latitude')
		JOIN `".DBPREFIX."postmeta` AS loc2 ON (wposts.ID = loc2.post_id AND loc2.meta_key = 'z_longitude')

		WHERE wposts.post_type = 'post'
		AND (wposts.post_status = 'publish' OR wposts.post_status = 'pending')
		AND wposts.post_parent = 0
		AND wposts.location_scraped = 0
		";
$result = mysql_query($sql);
$remaining = mysql_fetch_assoc($result);
if($remaining)
	$remaining = $remaining['cnt'];
else
	$remaining = '';
@mysql_free_result($result);


//get all pending and published results
	$sql = "SELECT wposts.ID AS postid, wposts.post_title,
			loc1.meta_value AS lat,
			loc2.meta_value AS lng

			FROM `".DBPREFIX."posts` AS wposts
			JOIN `".DBPREFIX."postmeta` AS loc1 ON (wposts.ID = loc1.post_id AND loc1.meta_key = 'z_latitude')
			JOIN `".DBPREFIX."postmeta` AS loc2 ON (wposts.ID = loc2.post_id AND loc2.meta_key = 'z_longitude')

			WHERE wposts.post_type = 'post'
			AND (wposts.post_status = 'publish' OR wposts.post_status = 'pending')
			AND wposts.post_parent = 0

			AND wposts.location_scraped = 0

			ORDER BY RAND()

			LIMIT 100
			";

$result = mysql_query($sql);

$num_results = mysql_num_rows($result);

if(!$num_results)
{
	die('No results found.');
}

if($remaining)
	output_progress('Remaining: '.$remaining.'<br />');

$useragent = array(
	'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.143 Safari/537.36',
	'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:31.0) Gecko/20100101 Firefox/31.0',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.143 Safari/537.36',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.78.2 (KHTML, like Gecko) Version/7.0.6 Safari/537.78.2',
	'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.94 Safari/537.36',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:31.0) Gecko/20100101 Firefox/31.0',
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit/537.77.4 (KHTML, like Gecko) Version/7.0.5 Safari/537.77.4',
);
$useragent = $useragent[rand(0,count($useragent)-1)];

$context = stream_context_create(array(
	'http' => array(
		'timeout'	=> 90,      // Timeout in seconds
		//'user_agent'=> $useragent,
	)
));
unset($useragent);

//$n = 1;
while($row = mysql_fetch_assoc($result))
{
	$output = '';
//	$output = $row['postid'] . ":<br />";

//	echo "#" . $n . " - ";
	echo "#<a href='/?p=" . $row['postid'] . "' target='_blank'>" . $row['postid'] . "</a>:<br />";
//	$n++;

	//echo $row['title'] . ": " . $row['lat'] . ", " .$row['lng'] . "<br />";

	//get the location

	$querystring = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&latlng=".$row['lat'].",".$row['lng'];

	$f = false;
	$f = file_get_contents($querystring, 0, $context);
	if(!$f) continue;

	$f = json_decode($f);

	if(!isset($f->status) || $f->status != 'OK')
	{
		$out = 'Could not retrieve location from Google.';

		if($f->status == 'OVER_QUERY_LIMIT')
		{
			output_progress('DAILY API QUOTA EXCEEDED - '. $out);

			//@mysql_free_result($result);
			//@mysql_close($conn);
			//die();
		}

		continue;
	}

	//var_dump($f);

	// I need:
	// - country
	// - city
	// - street


	$formatted_address = $street_number = $route = $locality = $country = $postal_code = $administrative_area_level_1 = $administrative_area_level_2 = '';

	$sublocality_level_4 = $sublocality_level_3 = $sublocality_level_2 = $sublocality_level_1 = '';

	$lat = $lng = '';
	$location_type = '';
	$viewport = '';


	//loop through the results until the correct value is found
	foreach($f->results as $result_entry)
	{
		//var_dump($result_entry);
		//die();

		if(isset($result_entry->address_components))
		{
			if(!$formatted_address && isset($result_entry->formatted_address))
				$formatted_address = $result_entry->formatted_address;

			if(!$lat && isset($result_entry->geometry))
				$lat = $result_entry->geometry->location->lat;
			if(!$lng && isset($result_entry->geometry))
				$lng = $result_entry->geometry->location->lng;

			if(!$location_type && isset($result_entry->geometry->location_type))
				$location_type = $result_entry->geometry->location_type;

			if(!$viewport && isset($result_entry->geometry->viewport))
				$viewport = serialize($result_entry->geometry->viewport);

			foreach($result_entry->address_components as $value)
			{
				if(!$street_number && isset($value->long_name) && $value->types[0] == 'street_number')
				$street_number = $value->long_name;

				if(!$route && isset($value->long_name) && $value->types[0] == 'route')
				$route = $value->long_name;

				if(!$locality && isset($value->long_name) && $value->types[0] == 'locality')
				$locality = $value->long_name;

				if(!$country && isset($value->long_name) && $value->types[0] == 'country')
				$country = $value->long_name;

				if(!$postal_code && isset($value->long_name) && $value->types[0] == 'postal_code')
				$postal_code = $value->long_name;

				if(!$administrative_area_level_2 && isset($value->long_name) && $value->types[0] == 'administrative_area_level_2')
				$administrative_area_level_2 = $value->long_name;

				if(!$administrative_area_level_1 && isset($value->long_name) && $value->types[0] == 'administrative_area_level_1')
				$administrative_area_level_1 = $value->long_name;

				if(!$sublocality_level_4 && isset($value->long_name) && $value->types[0] == 'sublocality_level_4')
				$sublocality_level_4 = $value->long_name;
				if(!$sublocality_level_3 && isset($value->long_name) && $value->types[0] == 'sublocality_level_3')
				$sublocality_level_3 = $value->long_name;
				if(!$sublocality_level_2 && isset($value->long_name) && $value->types[0] == 'sublocality_level_2')
				$sublocality_level_2 = $value->long_name;
				if(!$sublocality_level_1 && isset($value->long_name) && $value->types[0] == 'sublocality_level_1')
				$sublocality_level_1 = $value->long_name;

				if($route && $locality && $country)
					break 2;
			}
		}
	}

	$output .= $route."<br />";
	$output .= $locality."<br />";
	$output .= $administrative_area_level_2."<br />";
	$output .= $administrative_area_level_1."<br />";
	$output .= $country."<br />";


	//do not keep all this info in memory
	//write to DB instead and process it later

	mysql_query("REPLACE INTO `spotting_locations` (
					`postid`,
					`street_number`,
					`route`,
					`locality`,
					`administrative_area_level_2`,
					`administrative_area_level_1`,
					`postal_code`,
					`country`,
					`formatted_address`,

					`sublocality_level_4`,
					`sublocality_level_3`,
					`sublocality_level_2`,
					`sublocality_level_1`,

					`lat`,
					`lng`,

					`location_type`,
					`viewport`,

					`dateline`
					) VALUES (
						'".intval($row['postid'])."',
						'".mysql_real_escape_string($street_number)."',
						'".mysql_real_escape_string($route)."',
						'".mysql_real_escape_string($locality)."',
						'".mysql_real_escape_string($administrative_area_level_2)."',
						'".mysql_real_escape_string($administrative_area_level_1)."',
						'".mysql_real_escape_string($postal_code)."',
						'".mysql_real_escape_string($country)."',
						'".mysql_real_escape_string($formatted_address)."',

						'".mysql_real_escape_string($sublocality_level_4)."',
						'".mysql_real_escape_string($sublocality_level_3)."',
						'".mysql_real_escape_string($sublocality_level_2)."',
						'".mysql_real_escape_string($sublocality_level_1)."',

						'".mysql_real_escape_string($lat)."',
						'".mysql_real_escape_string($lng)."',

						'".mysql_real_escape_string($location_type)."',
						'".mysql_real_escape_string($viewport)."',

						'".time()."'
					)");

	//mark this location as scraped to prevent duplicates
	mysql_query("UPDATE ".DBPREFIX."posts SET location_scraped = 1 WHERE ID = '".intval($row['postid'])."' LIMIT 1");

	output_progress($output);

}

//memory
@mysql_free_result($result);

//close connection
@mysql_close($conn);

?>
</body>
</html>