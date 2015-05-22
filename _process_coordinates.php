<?php

error_reporting(E_ALL | E_NOTICE | E_STRICT);
//error_reporting(0);


define('__PATH', realpath(dirname(__FILE__)));

/*connect to database*/
require_once __PATH . "/db_connect.php";


	$sql = "SELECT DISTINCT wposts.ID AS ID, postmeta.meta_value AS url
FROM `".DBPREFIX."posts` AS wposts
JOIN `".DBPREFIX."postmeta` AS postmeta ON (wposts.ID = postmeta.post_id)
WHERE wposts.post_type = 'post'

AND postmeta.meta_key = 'url'

AND wposts.ID NOT IN (
	SELECT ".DBPREFIX."posts.ID FROM ".DBPREFIX."posts, `".DBPREFIX."postmeta` AS postmeta2
	WHERE ".DBPREFIX."posts.ID = postmeta2.post_id
	AND postmeta2.meta_key = 'z_latitude'
)
ORDER BY wposts.post_date DESC";
//		AND wposts.post_status != 'pending'

	$result = mysql_query($sql);

	$num_results = mysql_num_rows($result);

	if(!$num_results)
	{
		die('No new results.');
	}

	echo "# Results: $num_results<br /><br />";

	//go through the results
	//parse the url
	//add the meta data for the row
	while($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
		$coords = array();

		if(!empty($row['url']))
		{
			//get coords

			//STREETVIEW
			$matches = array();
			preg_match("~&cbll=(-?\d+\.?\d*),(-?\d+\.?\d*)&~", $row['url'], $matches);
			if(isset($matches[1]) && isset($matches[2]))
				$coords = array($matches[1],$matches[2]);
			unset($matches);

			if(empty($coords))
			{

				//STREETVIEW, second try
				$matches = array();
				preg_match("~&ll=(-?\d+\.?\d*),(-?\d+\.?\d*)&~", $row['url'], $matches);
				if(isset($matches[1]) && isset($matches[2]))
					$coords = array($matches[1],$matches[2]);
				unset($matches);

				if(empty($coords))
				{

					//NEW STREET VIEW
					//https://www.google.com/maps/@6.253324,-75.570185,3a,42.1y,57.35h,95.54t/data=!3m4!1e1!3m2!1sV_0oPo3n9k4caU-6L7dGjw!2e0
					$matches = array();
					preg_match("~@(-?\d+\.?\d*),(-?\d+\.?\d*),~", $row['url'], $matches);
					if(isset($matches[1]) && isset($matches[2]))
						$coords = array($matches[1],$matches[2]);
					unset($matches);

					if(empty($coords))
					{

						//BING
						//http://www.bing.com/maps/?v=2&cp=40.420891~-3.703267&lvl=7&dir=0&sty=x~lat~40.420891~lon~-3.703267~alt~728.568~z~30~h~179.1~p~-9~cz~0.168~pid~5082&app=5082&FORM=LMLTCC
						$matches = array();
						preg_match("@&cp\=(-?\d+\.?\d*)~(-?\d+\.?\d*)@", $row['url'], $matches);
						if(isset($matches[1]) && isset($matches[2]))
							$coords = array($matches[1],$matches[2]);
						unset($matches);
					}
				}
			}

		}
		else echo "[empty url] ";

		$bing = 0;

		if(!empty($row['url']) && empty($coords[0]))
		{
			echo "[could not find cbll=] trying again with ll=...<br>";


			//get coords
			$matches = array();
			preg_match("~&ll=(-?\d+\.?\d*),(-?\d+\.?\d*)~", $row['url'], $matches);
			if(isset($matches[1]) && isset($matches[2]))
			{
				$coords = array($matches[1],$matches[2]);
				$bing = 1;
			}
			unset($matches);
		}

		if(!empty($coords[0]) && !empty($coords[1]))
		{
			echo "$row[ID]: $coords[0], $coords[1]<br />";

			//add the coords as meta data

			$sql = "INSERT INTO `".DBPREFIX."postmeta`
				(`meta_id`, `post_id`, `meta_key`, `meta_value`)
				VALUES
				(
					NULL,
					'".intval($row['ID'])."',
					'z_latitude',
					'$coords[0]'
				),
				(
					NULL,
					'".intval($row['ID'])."',
					'z_longitude',
					'$coords[1]'
				)";
			if($bing == 1) $sql .= ",
				(
					NULL,
					'".intval($row['ID'])."',
					'bing',
					'1'
				)";

			$insert = mysql_query($sql);

			echo "$sql<br>";
			var_dump($insert);
			echo "<br><br>";

		}
		else
			echo "[could not find cbll or ll.]<br><br>";
	}


//memory
mysql_free_result($result);

//close connection
mysql_close($conn);

