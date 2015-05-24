<?php

error_reporting(E_ALL | E_NOTICE | E_STRICT);
//error_reporting(0);


/*connect to database*/
require_once "./db_connect.php";



//saving date-month
if(!empty($_POST['postid']) && !empty($_POST['error'])){

	if($_POST['error'] == 'not_found'){

		$result = mysql_query("
			UPDATE ".DBPREFIX."posts
			SET date_processed = 1
			WHERE ID = ".intval($_POST['postid'])."
			LIMIT 1
			"
		);
	}
	else
	{
		echo htmlspecialchars(strip_tags($_POST['error']));
		die;
	}
}
elseif(!empty($_POST['postid']) && !empty($_POST['year']) && !empty($_POST['month'])){

	$year = intval($_POST['year']);
	$month = intval($_POST['month']);

	$postid = intval($_POST['postid']);

	if($postid && $year && $month)
	{

		$result = mysql_query("
			INSERT INTO ".DBPREFIX."postmeta
			(
				post_id,
				meta_key,
				meta_value
			)
			VALUES
			(
				".$postid.",
				'image_year',
				".$year."
			)
		");

		$result = mysql_query("
			INSERT INTO ".DBPREFIX."postmeta
			(
				post_id,
				meta_key,
				meta_value
			)
			VALUES
			(
				".$postid.",
				'image_month',
				".$month."
			)
		");

		$result = mysql_query("
			UPDATE ".DBPREFIX."posts
			SET date_processed = 1
			WHERE ID = ".intval($postid)."
			LIMIT 1
			"
		);

		die('saved');

	}
	else
	{
		die('invalid parameter');
	}

}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
   <title>Street View Date Extractor</title>
   <meta http-equiv="content-type" content="text/html;charset=utf-8" />
   <meta http-equiv="Content-Style-Type" content="text/css" />
</head>
<body>
<?php



//POSTS TO PROCESS
	$sql = "SELECT DISTINCT wposts.ID AS id, postmeta.meta_value as panoid

FROM `".DBPREFIX."posts` AS wposts
JOIN `".DBPREFIX."postmeta` AS postmeta ON (wposts.ID = postmeta.post_id)

WHERE wposts.post_type = 'post'

AND postmeta.meta_key = 'panoid'
AND postmeta.meta_value <> ''

AND wposts.date_processed <> '1'

ORDER BY RAND()

LIMIT 1";

	$result = mysql_query($sql);

	$num_results = mysql_num_rows($result);

	if(!$num_results)
	{
		die('No results found.');
	}

	//echo "# Results: $num_results".($total ? "/".$total : "")."<br /><br />".PHP_EOL.PHP_EOL;

	$row = mysql_fetch_array($result, MYSQL_ASSOC);

	echo "<script>
		var postid = '".$row['id']."';
		var panoid = '".$row['panoid']."';
		</script>";


mysql_free_result($result);

mysql_close($conn);

?>


<script src="/js/jquery.min.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?v=3.exp"></script>
<script>


function callback(StreetViewPanoramaData, StreetViewStatus) {

	if(StreetViewPanoramaData !== null && StreetViewPanoramaData.imageDate !== undefined){

		var idate = StreetViewPanoramaData.imageDate;

		var res = idate.split("-");

		var year = parseInt(res[0], 10);
		var month = parseInt(res[1], 10);

		console.log('Month: ' + month);
		console.log('Year: ' + year);

		if(year && month) {

			//save the data, using the panoid as key to identify the post
			$.ajax({
			  type: "POST",
			  url: FULLURL."/data/extract_date.php",
			  data: { 'postid': postid, 'year': year, 'month': month },
			  success: function(msg) {
				console.log( msg );
			  }
			});

		} else {
			console.log('year/month not found');
		}

	} else {

		console.log('not found: ' + StreetViewStatus);

		//mark this post as parse to not query it again
		$.ajax({
		  type: "POST",
		  url: FULLURL."/data/extract_date.php",
		  data: { 'postid': postid, 'error': 'not_found' },
		  success: function(msg) {
			console.log( msg );
		  }
		});

	}

	//redirect (reload)
	window.location = FULLURL.'/data/extract_date.php';

}

$(document).ready(function(){
	var svc = new google.maps.StreetViewService;

	console.log('Postid: ' + postid);
	console.log('Panoid: ' + panoid);

	console.log('---');

	svc.getPanoramaById(panoid, callback);
});

</script>

</body>
</html>