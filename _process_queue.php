<?php

error_reporting(E_ALL | E_NOTICE | E_STRICT);
//error_reporting(0);

	define('LIMIT', (isset($_GET['limit']) ? intval($_GET['limit']) : 10));

	define('ORDER', (isset($_GET['order']) ? intval($_GET['order']) : 0));

	define('__PATH', realpath(dirname(__FILE__)));

	define('USERAGENT', "IE 7 - Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30)");

	function curl_img($source, $target){
		$ch = curl_init();
		$options = array(CURLOPT_HEADER => 0,
		CURLOPT_URL => $source,
		CURLOPT_FAILONERROR => true,
		CURLOPT_FOLLOWLOCATION => 0,
		CURLOPT_USERAGENT => USERAGENT,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60); // 1 minute timeout (should be enough)
		curl_setopt_array($ch, $options);
		$img = curl_exec($ch);
		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if($img && '200' == $http)
		{
			$fp = fopen($target, "wb");
			fwrite($fp, $img);
			fclose($fp);
			return true;
		}
		else
		{
//			echo htmlspecialchars($http);
			return false;
		}
	}


function toAscii($str) {
	$clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $str);
	$clean = strtolower(trim($clean, '-'));
	$clean = preg_replace("/[\/_|+ -]+/", '-', $clean);

	return $clean;
}


require_once(__PATH . "/thumbnail_functions.php");



?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="de">
<head>
   <title>Queue Processor</title>
   <meta http-equiv="content-type" content="text/html;charset=utf-8" />
   <meta http-equiv="Content-Style-Type" content="text/css" />
</head>
<body>
<div>Processes the submission queue, produces street view screenshot of 600x300px.</div>
<?php

/*connect to database*/
@require_once __PATH . "/db_connect.php";


$thumbnailsizes = get_thumbnail_sizes();


if(isset($_GET['empty']))
{
	$where_clause = "AND wposts.post_content = ''";
}
else
{
	$where_clause = "AND wposts.ID NOT IN (
		SELECT dm.post_id FROM ".DBPREFIX."postmeta AS dm WHERE dm.meta_key = '_thumbnail_id'
		)";
}


//TOTAL RESULTS
	$sql = "SELECT COUNT(wposts.ID) AS count
FROM `".DBPREFIX."posts` AS wposts
WHERE wposts.post_type = 'post'
AND wposts.post_status = 'pending'
$where_clause
";
$result = mysql_query($sql);
$total = 0;
while($row = mysql_fetch_array($result, MYSQL_ASSOC))
{
	$total = intval($row['count']);
}
mysql_free_result($result);

//die('total: '.intval($total));


//DATA TO PROCESS
	$sql = "SELECT wposts.ID AS `ID`,
	postmeta1.meta_value AS `url`,
	postmeta2.meta_value AS `youtube`,
	postmeta3.meta_value AS `panoid`
FROM `".DBPREFIX."posts` AS wposts
JOIN `".DBPREFIX."postmeta` AS postmeta1 ON (wposts.ID = postmeta1.post_id AND postmeta1.meta_key = 'url')
LEFT JOIN `".DBPREFIX."postmeta` AS postmeta2 ON (wposts.ID = postmeta2.post_id AND postmeta2.meta_key = 'youtube')
LEFT JOIN `".DBPREFIX."postmeta` AS postmeta3 ON (wposts.ID = postmeta3.post_id AND postmeta3.meta_key = 'panoid')
WHERE wposts.post_type = 'post'
AND wposts.post_status = 'pending'".
(!isset($_GET['empty']) ? "
	AND wposts.ID NOT IN (
	SELECT post_id FROM ".DBPREFIX."postmeta AS dm WHERE dm.meta_key = '_thumbnail_id'
	)
" : "
	AND wposts.post_content = ''
")."
ORDER BY ".(ORDER == 1 ? 'RAND()' : 'wposts.post_date DESC')."
LIMIT ".LIMIT;

	$result = mysql_query($sql);

	$num_results = mysql_num_rows($result);

	if(!$num_results)
	{
		die('No results found.');
	}

	echo "# Results: $num_results".($total ? "/".$total : "")."<br /><br />";


	//go through the results
	//parse the url
	//add the meta data for the row
	while($row = mysql_fetch_array($result, MYSQL_ASSOC))
	{
		echo " <a href='".FULLURL."/blog/wp-admin/post.php?post=".intval($row['ID'])."&action=edit' target='_blank'>".intval($row['ID'])."</a> ";

		echo " <a href='".FULLURL."/?p=".intval($row['ID'])."' target='_blank'>visit</a> ";

		if(!empty($row['url']) || !empty($row['youtube']))
		{
			$querystring = '';
			$youtube_code = '';

			//GET ~YOUTUBE~ THUMBNAIL
			if(!empty($row['youtube']))
			{
					$youtube_code = $row['youtube'];
					$post['ID'] = $row['ID'];

					//fetch and store this thumbnail

//					echo " ".htmlspecialchars($youtube_code)." ";

					$ytthumburl = 'http://i3.ytimg.com/vi/'.$youtube_code.'/hqdefault.jpg';

					$yt_screenshot_name  = str_replace('/', '', $youtube_code);
					$yt_screenshot_name  = 'yt_' . str_replace('.', '', $yt_screenshot_name) . '.jpg';

					$yt_screenshot_path = realpath(__PATH.'/../uploads').'/'.$yt_screenshot_name;

/*
					//download screenshot
					if(!file_exists($yt_screenshot_path))
					{
						$yt_res = curl_img($ytthumburl, $yt_screenshot_path);
					}
*/

					//$img = image_resize_custom($yt_screenshot_path, array(200,100), true);
					//var_dump($img);
					//die(0);

					//show the image
					echo "<a href='http://www.youtube.com/watch?v=".$youtube_code."' target='_blank'><img src='".$ytthumburl."' alt='' width='150' border='0' /></a>";

					//store the thumbnail in db
					//(later)
					$querystring = $ytthumburl;

					//skip the rest
//					echo "<br/>";
//					continue;

				$name_url = 'dx_yt_'.$youtube_code.'.jpg';

			} //youtube


			//not youtube
			if($querystring == '')
			{
				//not a video

				//get street view of this location
				//extract the streetview info from url

				$vars = array();

				//explanation:

				// https://maps.google.com/maps?ll=(6.253334=LAT),(-75.570205=LNG)&spn=0.18,0.3&cbll=6.253334,-75.570205&layer=c&panoid=lEdw0vr9BDCMwPk7IUMMBQ&cbp=(),(32.71=HEADING),(),(3=ZOOM),(3.949997=PITCH)&output=classic&dg=ntvo

				//FOR OLD SV LINK:
					//$matches[1] = LAT
					//$matches[2] = LNG

					//$matches[3] = HEMISPHERE?	()
					//$matches[4] = HEADING		(32.71)
					//$matches[5] =	?			()
					//$matches[6] =	ZOOM		(3)
					//$matches[7] = PITCH		(3.949997)

				//https://www.google.com/maps/@(6.253334),(-75.570205),(3)a,(15)y,(32.71)h,(86.05)t/data=!3m4!1e1!3m2!1slEdw0vr9BDCMwPk7IUMMBQ!2e0

				//FOR NEW SV LINK:
					//$matches[1] = LAT
					//$matches[2] = LNG

					//$matches[3] = NOT USED	(3a)
					//$matches[4] = ZOOM/FOV	(15y)	 (0...90: kleine Zahl = mehr Zoom)
					//$matches[5] = HEADING		(32.71h)
					//$matches[6] = TILT/PITCH	(86.05t) (convert this to 90-pitch)

				$matches = array();

				if(!preg_match("~cbll=(\-?\d+\.?\d*),(\-?\d+\.?\d*).*cbp=(\-?\d*),(\-?\d*\.?\d*),(\-?\d*\.?\d*),(\-?\d*\.?\d*),(\-?\d*\.?\d*)~i", $row['url'], $matches))
				{

					$matches = array();

					if(!preg_match("~@(\-?\d+\.?\d*),(\-?\d+\.?\d*),(\d)a,(\-?\d+\.?\d*)y,(\-?\d+\.?\d*)h,(\-?\d+\.?\d*)t~i", $row['url'], $matches))
					{
						echo "Neither new nor old streetview link detected - skipping...<br />";
						continue;
					}
					else
					{
						/***NEW SV LINK***/
						$vars['lat'] = floatval($matches[1]);
						$vars['lng'] = floatval($matches[2]);

						$vars['zoom'] = floatval($matches[3]);
						$vars['heading'] = floatval($matches[5]);
						$vars['pitch'] = round(90 - floatval($matches[6]),2);
						//reverse the pitch
						$vars['pitch'] = (-1) * $vars['pitch'];

						$vars['fov'] = floatval($matches[4]);
						/***----NEW----***/
					}
				}
				else
				{
					/***OLD SV LINK***/
					$vars['lat'] = floatval($matches[1]);
					$vars['lng'] = floatval($matches[2]);

					$vars['hemisphere'] = intval($matches[3]);

					$vars['zoom'] = intval($matches[6]);
					$vars['heading'] = floatval($matches[4]);
					$vars['pitch'] = floatval($matches[7]);

					//reverse the pitch if hemisphere
					//deprecated?
					$vars['pitch'] = ($vars['hemisphere'] == '12' ? ((-1) * abs($vars['pitch'])) : abs($vars['pitch']) );

					//zoom determines fov
					switch($vars['zoom'])
					{
						case '0':
							$vars['fov'] = '90';
						break;
						case '1':
						default:
							$vars['fov'] = '75';
						break;
						case '2':
							$vars['fov'] = '30';
						break;
						case '3':
							$vars['fov'] = '20';
						break;
					}
					/***----OLD----***/
				}

				//panoid is preferred
				if(isset($row['panoid']))
					$idstring = "pano=".$row['panoid'];
				else
					$idstring = "location=".$vars['lat'].",".$vars['lng'];


				$querystring = "http://maps.googleapis.com/maps/api/streetview?size=600x300&".$idstring."&heading=".$vars['heading']."&fov=".$vars['fov']."&pitch=".$vars['pitch']."&sensor=false";

				echo " <a href='".htmlspecialchars($row['url'],ENT_QUOTES)."' target='_blank'>SVLINK</a> ";

				echo "<a href='".$querystring."'>".$querystring."</a><br/>";

				$name_url = 'dx_streetview_'.$vars['lat'].','.$vars['lng'].'_'.$vars['zoom'].'a,'.$vars['fov'].'y,'.$vars['heading'].'h,'.$vars['pitch'].'t.jpg';

			}//querystring


			//save
			if($querystring)
			{
				$target = realpath(dirname(__FILE__).'/../uploads').'/'.$name_url;

				//download screenshot
				if(!file_exists($target))
				{
					$img_res = curl_img($querystring, $target);
					if($img_res)
					{

						//show the image
						echo "<img src='/uploads/".$name_url."' alt='' border='0' />";

						$guid = FULLURL."/uploads/".$name_url;

						//create the different thumbnail sizes

							//get post info
							$post = array();
							$sql = "SELECT * FROM `".DBPREFIX."posts` WHERE `ID` = '".intval($row['ID'])."'";
							$query = mysql_query($sql);
							while($ram = mysql_fetch_array($query, MYSQL_ASSOC))
							{
								$post['ID'] = intval($ram['ID']);
								$post['post_author'] = $ram['post_author'];
								$post['post_date'] = $ram['post_date'];
								$post['post_date_gmt'] = $ram['post_date_gmt'];
								$post['post_title'] = $ram['post_title'] . " Street View";
								$post['post_status'] = $ram['post_status'];
								$post['post_content'] = $ram['post_content'];
								$post['comment_status'] = $ram['comment_status'];
								$post['ping_status'] = $ram['ping_status'];
								$post['post_modified'] = $ram['post_modified'];
								$post['post_modified_gmt'] = $ram['post_modified_gmt'];
								$post['post_type'] = $ram['post_type'];
							}

							//insert original into database


							$post['slug'] = toAscii($post['post_title']);

							$sql = "INSERT INTO `".DBPREFIX."posts` (
										`ID`,
										`post_author`,
										`post_date`,
										`post_date_gmt`,
										`post_title`,
										`post_status`,
										`comment_status`,
										`ping_status`,
										`post_name`,
										`post_modified`,
										`post_modified_gmt`,
										`post_parent`,
										`guid`,
										`menu_order`,
										`post_type`,
										`post_mime_type`,
										`comment_count`
									) VALUES (
										NULL,
										'".mysql_real_escape_string($post['post_author'])."',
										'".mysql_real_escape_string($post['post_date'])."',
										'".mysql_real_escape_string($post['post_date'])."',
										'".mysql_real_escape_string($post['post_title'])."',
										'inherit',
										'open',
										'closed',
										'".mysql_real_escape_string($post['slug'])."',
										'".mysql_real_escape_string($post['post_modified'])."',
										'".mysql_real_escape_string($post['post_modified'])."',
										".mysql_real_escape_string($post['ID']).",
										'".mysql_real_escape_string($guid)."',
										0,
										'attachment',
										'image/jpeg',
										0
									)";
							$s = mysql_query($sql);
							if(!$s){
								echo "Error inserting into db. skipping...";
								continue;
							}



							$attachment_id = mysql_insert_id($conn);



							if($attachment_id)

							//create thumbnails
							if(image_resize_custom($target, array($thumbnailsizes['thumbnail_size_w'],$thumbnailsizes['thumbnail_size_h']))
								&& image_resize_custom($target, array($thumbnailsizes['medium_size_w'],$thumbnailsizes['medium_size_h']))
								&& image_resize_custom($target, array($thumbnailsizes['large_size_w'],$thumbnailsizes['large_size_h']))
							)
							{
								//store thumbnail in database

								//get name, without extension
								$info = pathinfo($target);
								$imagename =  basename($target,'.'.$info['extension']);

	/*
	//large is not necessary (same size as original)
								$targeturl = '/uploads/' . $imagename . '-' . intval($thumbnailsizes['large_size_w']) .'x'. intval($thumbnailsizes['large_size_h']) . '.jpg';
	//							echo " <img src='".$targeturl."' alt='' border='0' />";
	*/


								$targeturl = '/uploads/' . $imagename . '-' . intval($thumbnailsizes['medium_size_w']) .'x'. intval($thumbnailsizes['medium_size_h']) . '.jpg';
								echo " <img src='".$targeturl."' alt='' border='0' />";



								$targeturl = '/uploads/' . $imagename . '-' . intval($thumbnailsizes['thumbnail_size_w']) .'x'. intval($thumbnailsizes['thumbnail_size_h']) . '.jpg';
								echo " <img src='".$targeturl."' alt='' border='0' />";

								//insert meta


								$sql = "INSERT INTO `".DBPREFIX."postmeta` (
											`meta_id`,
											`post_id`,
											`meta_key`,
											`meta_value`
										) VALUES (
											NULL,
											'".mysql_real_escape_string($attachment_id)."',
											'_wp_attached_file',
											'".mysql_real_escape_string($imagename.".jpg")."'
										)";

								$s = mysql_query($sql);
								if(!$s){
									echo "Error inserting _wp_attached_file into db. skipping...";
									continue;
								}

								$origsize = getimagesize( $target );
								$thumbsize = getimagesize( realpath(dirname(__FILE__).'/../uploads').'/'.$imagename . '-' . intval($thumbnailsizes['thumbnail_size_w']) .'x'. intval($thumbnailsizes['thumbnail_size_h']) . '.jpg' );
								$mediumsize = getimagesize( realpath(dirname(__FILE__).'/../uploads').'/'.$imagename . '-' . intval($thumbnailsizes['medium_size_w']) .'x'. intval($thumbnailsizes['medium_size_h']) . '.jpg' );
								$metacrap = serialize(
											array (
											  'width'	=>	''.$origsize[0],
											  'height'	=>	''.$origsize[1],

											  'hwstring_small' => "height='".intval($thumbsize[1])."' width='".intval($thumbsize[0])."'",

											  'file' => ''.basename($target),

											  'sizes' =>
											  array (
												'thumbnail' =>
												array (
												  'file' => $imagename . '-' . intval($thumbnailsizes['thumbnail_size_w']) .'x'. intval($thumbnailsizes['thumbnail_size_h']) . '.jpg',
												  'width' => ''.intval($thumbsize[0]),
												  'height' => ''.intval($thumbsize[1]),
												),
												'medium' =>
												array (
												  'file' => $imagename . '-' . intval($thumbnailsizes['medium_size_w']) .'x'. intval($thumbnailsizes['medium_size_h']) . '.jpg',
												  'width' => ''.intval($mediumsize[0]),
												  'height' => ''.intval($mediumsize[1]),
												),
											  ),
											  'image_meta' =>
											  array (
												'aperture' => '0',
												'credit' => '',
												'camera' => '',
												'caption' => '',
												'created_timestamp' => '0',
												'copyright' => '',
												'focal_length' => '0',
												'iso' => '0',
												'shutter_speed' => '0',
												'title' => '',
											  ),
											)
										);

								$sql = "INSERT INTO `".DBPREFIX."postmeta` (
											`meta_id`,
											`post_id`,
											`meta_key`,
											`meta_value`
										) VALUES (
											NULL,
											'".mysql_real_escape_string($attachment_id)."',
											'_wp_attachment_metadata',
											'".mysql_real_escape_string($metacrap)."'
										)";

								$s = mysql_query($sql);
								if(!$s){
									echo "Error inserting metacrap into db. skipping...";
									continue;
								}


							}


							//set thumbnail of post

	//does attachment need to be inserted in post table???




							$sql = "INSERT INTO `".DBPREFIX."postmeta` (
										`meta_id`,
										`post_id`,
										`meta_key`,
										`meta_value`
									) VALUES (
										NULL,
										'".mysql_real_escape_string($post['ID'])."',
										'_thumbnail_id',
										'".mysql_real_escape_string($attachment_id)."'
									)";

							$s = mysql_query($sql);
							if(!$s){
								echo "Error inserting post thumbnail into db. skipping...";
								continue;
							}




							//insert image into post
							//do not overwrite content, if exists
							if(empty($post['post_content']))
							{
								$origurl = '/uploads/' . $imagename . '.jpg';

								$post['post_content'] = '<a href="'.FULLURL.'/?attachment_id='.intval($attachment_id).'" rel="attachment wp-att-'.intval($attachment_id).'"><img src="'.FULLURL.$origurl.'" alt="'.$post['post_title'].'" title="'.$post['post_title'].'" width="'.intval($origsize[0]).'" height="'.intval($origsize[1]).'" class="aligncenter size-full wp-image-'.intval($attachment_id).'" /></a>';

								$sql = "UPDATE `".DBPREFIX."posts`
										SET `post_content` = '".mysql_real_escape_string($post['post_content'])."'
										WHERE `ID` = '".mysql_real_escape_string($post['ID'])."'";
								mysql_query($sql);
							}


							//time to skip to next queue item

							echo "<br/>";
							continue;

					}
					else
					{
						echo "Could not retrieve image. skipping...<br/>";
						continue;
					}

			} //target
			else
			{
					echo "Image already exists in /uploads/! skipping...<br/>";
			}

		}//querystring







			echo "<br/>";
		}
		else
		{
			echo "[url not found] <br />";
			continue;
		}
	}


//memory
mysql_free_result($result);

//close connection
mysql_close($conn);

?>
</body>
</html>