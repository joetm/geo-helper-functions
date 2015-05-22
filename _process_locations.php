<?php

//error_reporting(E_ALL | E_NOTICE | E_STRICT);
error_reporting(0);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="de">
<head>
   <title>Location Processor</title>
   <meta http-equiv="content-type" content="text/html;charset=utf-8" />
   <meta http-equiv="Content-Style-Type" content="text/css" />
</head>
<body>
<?php

define('__PATH', realpath(dirname(__FILE__)));

/*connect to database*/
require_once __PATH . "/db_connect.php";

//TOTAL RESULTS
	$sql = "SELECT DISTINCT COUNT(wposts.ID) AS count
FROM `".DBPREFIX."posts` AS wposts
JOIN `".DBPREFIX."postmeta` AS postmeta1 ON (wposts.ID = postmeta1.post_id)
JOIN `".DBPREFIX."postmeta` AS postmeta2 ON (wposts.ID = postmeta2.post_id)

WHERE wposts.post_type = 'post'
AND wposts.post_status = 'pending'
AND postmeta1.meta_key = 'z_latitude'
AND postmeta2.meta_key = 'z_longitude'
AND wposts.post_parent = 0

AND wposts.ID NOT IN (
	SELECT ".DBPREFIX."posts.ID FROM ".DBPREFIX."posts, `".DBPREFIX."postmeta` AS loc
	WHERE ".DBPREFIX."posts.ID = loc.post_id
	AND loc.meta_key = 'location'
)";
$result = mysql_query($sql);
$total = mysql_fetch_array($result, MYSQL_ASSOC);
$total = intval($row['count']);
mysql_free_result($result);


//DATA TO PROCESS
	$sql = "SELECT DISTINCT wposts.ID AS ID, postmeta1.meta_value AS lat, postmeta2.meta_value AS lng
FROM `".DBPREFIX."posts` AS wposts
JOIN `".DBPREFIX."postmeta` AS postmeta1 ON (wposts.ID = postmeta1.post_id)
JOIN `".DBPREFIX."postmeta` AS postmeta2 ON (wposts.ID = postmeta2.post_id)

WHERE wposts.post_type = 'post'
AND wposts.post_status = 'pending'
AND postmeta1.meta_key = 'z_latitude'
AND postmeta2.meta_key = 'z_longitude'
AND wposts.post_parent = 0

AND wposts.ID NOT IN (
	SELECT ".DBPREFIX."posts.ID FROM ".DBPREFIX."posts,`".DBPREFIX."postmeta` AS loc
	WHERE ".DBPREFIX."posts.ID = loc.post_id
	AND loc.meta_key = 'location'
	AND loc.meta_value <> ''
)
ORDER BY wposts.post_date DESC
LIMIT 40";

//		AND wposts.post_status != 'pending'

	$result = mysql_query($sql);

	$num_results = mysql_num_rows($result);

	if(!$num_results)
	{
		die('No results found.');
	}

	echo "# Results: $num_results".($total ? "/".$total : "")."<br /><br />";

	$context = stream_context_create(array(
		'http' => array(
			'timeout' => 90      // Timeout in seconds
		)
	));

	//go through the results
	//parse the url
	//add the meta data for the row
	while($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
		//echo $row['ID'].", ";

		$location = '';

		if(isset($row['lat']) && isset($row['lng']))
		{

			echo "#<a href='".FULLURL."/?p=".$row['ID']."'>" . $row['ID'] . "</a>: " . $row['lat'] . "," . $row['lng'] . ": ";

			//call google with these coordinates to find the location

			$querystring = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&latlng=".$row['lat'].",".$row['lng'];

//echo $querystring . "<br/>";

			$f = false;
			$f = file_get_contents($querystring, 0, $context);
			if(!$f) continue;

			$f = json_decode($f);

			if(!isset($f->status) || $f->status != 'OK') continue;

//var_dump($f);
			$f = $f->results[0];
			$location = strip_tags($f->formatted_address);

//			echo $location."<br/><br/>";

		}
		else echo "[lat/lng not found] <br />";


		if(!empty($location))
		{

			echo "<strong>".htmlspecialchars(strip_tags($location))."</strong>";

			//add the location as meta data
			$sql = "INSERT INTO `".DBPREFIX."postmeta`
				(`meta_id`, `post_id`, `meta_key`, `meta_value`)
				VALUES
				(
					NULL,
					".intval($row['ID']).",
					'location',
					'".mysql_real_escape_string($location)."'
				)";
			if($location)	$insert = mysql_query($sql);
			if($insert)	echo " - OK<br />";
		}
		else
			echo " - FAIL<br />";
	}


//memory
mysql_free_result($result);

//close connection
mysql_close($conn);

?>
</body>
</html>