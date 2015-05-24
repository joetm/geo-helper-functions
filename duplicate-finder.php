<?php

error_reporting(E_ALL | E_NOTICE | E_STRICT);
//error_reporting(0);

define('__PATH', realpath(dirname(__FILE__)));

//load wp
//require_once('../blog/wp-load.php');

/*connect to database*/
@require_once __PATH . "/db_connect.php";



//unreviewed post count
$todocount = 0;
$r = mysql_query("SELECT COUNT(ID) AS cnt
FROM ".DBPREFIX."posts
WHERE
	post_parent = 0
	AND post_status IN ('pending', 'publish')
	AND post_type = 'post'
	AND disparate = 0
");
if($r){
	$todocount = mysql_fetch_assoc($r);
	if(isset($todocount['cnt'])) $todocount = $todocount['cnt'];
}
@mysql_free_result($r);


//completion marking
if(isset($_POST['do']) && $_POST['do'] == 'mark-complete' && !empty($_POST['complete']))
{

	$complete_ids = array();
	foreach($_POST['complete'] as $id)
	{
		if($id)
			$complete_ids[] = intval($id);
	}

	//var_dump($complete_ids);
	//die();

	foreach($complete_ids as $i)
	{
		mysql_query("UPDATE ".DBPREFIX."posts
					SET disparate = '1'
					WHERE ID = '".intval($i)."'
					LIMIT 1");
	}

	unset($complete_ids, $_POST['complete']);

	die('OK');

}//mark-complete

?>
<!DOCTYPE html>
<html>
<head>
<style>

img{max-width:300px;max-height:155px;border:3px solid white;}
.subimg{float:left;fonct-size:0.8em;}

.parent-highlight{border:3px solid #FF0000;}
.img-tiny{width:50px;height:auto;}

.clearfix:after,.clearfix:before{display:table;content:" "}
.clearfix:after{clear:both}

#dialog-confirm{
	display:none;
	width:200px;
}

.ui-button{
	width:30%;
}
.dialog-confirm{display:none}
.ui-widget-content{border:0px;}
</style>

<link rel="stylesheet" href="http://code.jquery.com/ui/1.11.3/themes/smoothness/jquery-ui.css">

</head>
<body>


<div style="margin:0px 0px 20px 0px">
To do: <?php echo $todocount ?>
</div>


	<div id="dialog-confirm" title="Action"></div>


<?php




$grid_size = 0.00020;


$sql = "
	SELECT DISTINCT
		dp.ID AS ID,
		th.guid AS thumbnail,
		mo.meta_value AS image_month,
		ye.meta_value AS image_year,
		lt.meta_value AS lat,
		lg.meta_value AS lng

	FROM ".DBPREFIX."posts AS dp

		LEFT JOIN ".DBPREFIX."postmeta AS mo ON (mo.post_id = dp.ID AND mo.meta_key = 'image_month')
		LEFT JOIN ".DBPREFIX."postmeta AS ye ON (ye.post_id = dp.ID AND ye.meta_key = 'image_year')

		LEFT JOIN ".DBPREFIX."postmeta AS lt ON (lt.post_id = dp.ID AND lt.meta_key = 'z_latitude')
		LEFT JOIN ".DBPREFIX."postmeta AS lg ON (lg.post_id = dp.ID AND lg.meta_key = 'z_longitude')

		JOIN ".DBPREFIX."postmeta AS thm ON (thm.post_id = dp.ID AND thm.meta_key = '_thumbnail_id')
		JOIN ".DBPREFIX."posts AS th ON (thm.meta_value = th.ID AND th.post_type = 'attachment')

		INNER JOIN ".DBPREFIX."term_relationships AS tag_term_relationships ON (dp.ID = tag_term_relationships.object_id)
		INNER JOIN ".DBPREFIX."term_taxonomy AS tag_term_taxonomy ON (tag_term_relationships.term_taxonomy_id = tag_term_taxonomy.term_taxonomy_id AND tag_term_taxonomy.taxonomy = 'post_tag')
		INNER JOIN ".DBPREFIX."terms AS tag_terms ON (tag_term_taxonomy.term_id = tag_terms.term_id)
	";

if (!empty($_GET['cat']))
{
	$sql .= "INNER JOIN ".DBPREFIX."term_relationships AS cat_term_relationships ON (dp.ID = cat_term_relationships.object_id)
	INNER JOIN ".DBPREFIX."term_taxonomy AS cat_term_taxonomy ON (cat_term_relationships.term_taxonomy_id = cat_term_taxonomy.term_taxonomy_id AND cat_term_taxonomy.taxonomy = 'category')
	INNER JOIN ".DBPREFIX."terms AS cat_terms ON (cat_term_taxonomy.term_id = cat_terms.term_id)";
}

$sql .= "
	WHERE

	dp.post_parent = 0
	AND dp.post_status IN ('pending', 'publish')
	AND dp.post_type = 'post'

	AND dp.disparate = 0
";

if(isset($_GET['p']) && $_GET['p'])
{
	$sql .= " AND dp.ID = " . intval($_GET['p']);
}

if (!empty($_GET['cat']))
{
	$sql .= " AND cat_terms.slug = '".mysql_real_escape_string($_GET['cat'])."'";
}

$sql .= "
	AND tag_terms.term_id NOT IN (1122, 599, 107)

	ORDER BY RAND()

	LIMIT 50
";

//die($sql);
$result = mysql_query($sql);

if($result){

	while($row = mysql_fetch_assoc($result))
	{

		//check if this image has children
		$sres = mysql_query("SELECT COUNT(ID) AS cnt FROM ".DBPREFIX."posts WHERE post_parent <> 0 AND post_type = 'post' AND post_parent = '" . mysql_real_escape_string($row['ID']) . "'");
		if($sres){
			$cnt = mysql_fetch_assoc($sres);
			$cnt = $cnt['cnt'];
			$row['children'] = $cnt;
		}
		else
		{
			$row['children'] = 0;
		}
		@mysql_free_result($sres);

		$parent = '';
		$children = '';
		$ids = array();


		$grid = array();
		$grid['xl'] = floatval($row['lat']) - $grid_size;
		$grid['xr'] = floatval($row['lat']) + $grid_size;
		$grid['yb'] = floatval($row['lng']) - ($grid_size*1.8);
		$grid['yt'] = floatval($row['lng']) + ($grid_size*1.8);

		//var_dump($grid);

		//sub query
		$sql = "
			SELECT DISTINCT
				dp.ID AS ID,
				mo.meta_value AS image_month,
				ye.meta_value AS image_year,
				th.guid AS thumbnail

			FROM ".DBPREFIX."posts AS dp

				LEFT JOIN ".DBPREFIX."postmeta AS mo ON (mo.post_id = dp.ID AND mo.meta_key = 'image_month')
				LEFT JOIN ".DBPREFIX."postmeta AS ye ON (ye.post_id = dp.ID AND ye.meta_key = 'image_year')

				JOIN ".DBPREFIX."postmeta AS lt ON (lt.post_id = dp.ID AND lt.meta_key = 'z_latitude')
				JOIN ".DBPREFIX."postmeta AS lg ON (lg.post_id = dp.ID AND lg.meta_key = 'z_longitude')

				LEFT JOIN ".DBPREFIX."postmeta AS thm ON (thm.post_id = dp.ID AND thm.meta_key = '_thumbnail_id')
				LEFT JOIN ".DBPREFIX."posts AS th ON (thm.meta_value = th.ID AND th.post_type = 'attachment')

				INNER JOIN ".DBPREFIX."term_relationships AS tag_term_relationships ON (dp.ID = tag_term_relationships.object_id)
				INNER JOIN ".DBPREFIX."term_taxonomy AS tag_term_taxonomy ON (tag_term_relationships.term_taxonomy_id = tag_term_taxonomy.term_taxonomy_id AND tag_term_taxonomy.taxonomy = 'post_tag')
				INNER JOIN ".DBPREFIX."terms AS tag_terms ON (tag_term_taxonomy.term_id = tag_terms.term_id)
				";

			if (!empty($_GET['cat']))
			{
				$sql .= "INNER JOIN ".DBPREFIX."term_relationships AS cat_term_relationships ON (dp.ID = cat_term_relationships.object_id)
				INNER JOIN ".DBPREFIX."term_taxonomy AS cat_term_taxonomy ON (cat_term_relationships.term_taxonomy_id = cat_term_taxonomy.term_taxonomy_id AND cat_term_taxonomy.taxonomy = 'category')
				INNER JOIN ".DBPREFIX."terms AS cat_terms ON (cat_term_taxonomy.term_id = cat_terms.term_id)";
			}

			$sql .= " WHERE

				dp.post_type = 'post'
				AND dp.post_parent = 0
				AND dp.post_status IN ('pending', 'publish') ";

			if (!empty($_GET['cat']))
			{
				$sql .= " AND cat_terms.slug = '".mysql_real_escape_string($_GET['cat'])."'";
			}

			$sql .= "
				AND lt.meta_value BETWEEN " . $grid['xl'] ." AND " . $grid['xr'] ."
				AND lg.meta_value BETWEEN " . $grid['yb'] ." AND " . $grid['yt'] ."

				AND tag_terms.term_id NOT IN (1122,599,107)

				AND dp.disparate = 0

				AND dp.ID NOT LIKE ".intval($row['ID'])."

			LIMIT 30
		";

		//die($sql);
		$subresult = mysql_query($sql);
		if($subresult){

			//skip posts that have no nearby spottings
			$sub_count = mysql_num_rows($subresult);
			if(!$sub_count) continue;

			//parent ID
			$ids[] = $row['ID'];

			/***PARENT***/
			$parent = '<div style="'.($row['children'] ? 'font-weight:bold;color:red;' : 'color:#000000').'">

				<div style="float:right">
					<input type="hidden" value="'. $row['ID'] .'" name="complete[]" />
					<input type="hidden" value="mark-complete" name="do" />
					<input type="submit" value="mark complete" />
				</div>

				POST <a target="blank" href="'.FULLURL.'/?p=' . $row['ID'] . '">' . $row['ID'] . '</a>
				- ' . $row['image_month'] . '-' . $row['image_year'];
			$parent .= ' - #rel: ' . intval($sub_count) . ' - childs: '.$row['children'];
			$parent .= "</div>";

			$parent .= "<img data-id='".$row['ID']."' src='".$row['thumbnail']."'
							class='". ($row['children'] ? 'isparent' : '') ."' alt='' />";
			/***PARENT***/

			$children = "<div class='subimgs clearfix'>";

			while($subrow = mysql_fetch_assoc($subresult))
			{

				//check if this image has children
				$sres = mysql_query("SELECT COUNT(ID) AS cnt FROM ".DBPREFIX."posts WHERE post_parent <> 0 AND post_type = 'post' AND post_parent = '" . mysql_real_escape_string($subrow['ID']) . "'");
				if($sres){
					$cnt = mysql_fetch_assoc($sres);
					$cnt = $cnt['cnt'];
					$subrow['children'] = $cnt;
				}
				else
				{
					$subrow['children'] = 0;
				}
				@mysql_free_result($sres);

				$children .= "<div class='subimg' style='margin-left:40px;". ($subrow['children'] ? "font-weight:bold;color:red;" : "color:#000000") . "'>";
				$children .= ' - <a target="blank" href="'.FULLURL.'/?p=' . $subrow['ID'] . '">' . $subrow['ID'] . '</a> - ' . $row['image_month'] . '-' . $row['image_year'];
				$children .= ' - childs: '.$subrow['children'];
					// - ' . $subrow['image_month'] . '-' . $subrow['image_year'];
				$children .= "<br />";
				$children .= "<img data-id='".$subrow['ID']."' src='".$subrow['thumbnail']."'
								class='".($subrow['children'] ? 'isparent' : '')."' alt='' />";

				$children .= '<input type="hidden" value="'. $subrow['ID'] .'" name="complete[]" />';

				$children .= "</div>";

				//child IDs
				$ids[] = $subrow['ID'];

			}//while

			$children .= "</div>";

		}
		@mysql_free_result($subresult);

		$children .= "<hr />";

/*
		//get the number of children for each post
		if(!empty($ids))
		{
			$sql = "
				SELECT ID, COUNT(ID) AS cnt
				FROM ".DBPREFIX."posts
				WHERE post_status IN ('pending', 'publish')
				AND post_parent IN (" . implode(',', $ids) . ")
			";
			//die($sql);
			$re = mysql_query();
			if($re)
			{
				while($r = mysql_fetch_assoc($re))
				{
					echo $r['ID'] . ' - ' . $r['post_parent'] . ' - ' . $r['cnt'];
				}
			}
			mysql_free_result($re);
		}
*/

		echo '<form action="./duplicate-finder.php';
		if(!empty($_GET['cat']))
		{
			echo '?cat=' . htmlspecialchars($_GET['cat'], ENT_QUOTES);
		}
		echo '" method="post">';
		echo $parent;
		echo $children;
		echo "</form>";

		//die('xxx');


	}//while


}
@mysql_free_result($result);



?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/jquery-ui.min.js"></script>

<script>
$(function() {

	$('form').submit(function(e){

		e.preventDefault();

		var form = this;

		var data = $(this).serialize();

		//console.log(data);

		//------------
		$.ajax({
		  url: "./duplicate-finder.php",
		  cache: false,
		  type: "POST",
		  'data': data
		})
		.done(function( msg ) {

			if(msg == 'OK'){
				//hide the section of the page that was marked complete
				$(form).remove();
			}

		});
		//------------

		return false;

	});




	$('img').mousedown(function(event) {

		if(event.which == 3)
		{
			event.preventDefault();
			event.stopPropagation();

			//alert('Right Mouse button pressed.');

			//show action dialogue

			var the_post_id = $(this).data('id');

			var img_element = this;
			//console.log(img_element);

			if(the_post_id){

				$( "#dialog-confirm" ).dialog({
					  resizable: false,
					  height: 140,
					  width: 400,
					  modal: true,
					  buttons: {
						"Delete": function() {
						  $( this ).dialog( "close" );
						},
						"Private": function() {

							var dg = this;

							//------------
							$.ajax({
							  url: "./set_private.php",
							  cache: false,
							  type: "POST",
							  data: {
								'post_id': the_post_id
							  }
							})
							.done(function( msg ) {
								$(img_element).remove();
								$( dg ).dialog( "close" );
								alert(msg);
							});
							//------------

						},
						Cancel: function() {
						  $( this ).dialog( "close" );
						}
					  }
				});
			}

		}

		return false;

	});

	//$( "body" ).contextmenu( function() { return false; });

    $( "img:not(.isparent)" ).draggable({
	    start: function(event, ui) {
	    	$(this).css("z-index", 999);
	    }
    });

    $( "img" ).droppable({
      drop: function( event, ui ) {

        $( this ).addClass( "parent-highlight" );

		var parent_id	= $(this).data('id');
		var sub_id		= $(ui.draggable).data('id');

		if (parent_id && sub_id) {
			//ajax merge
			$.ajax({
				type: "POST",
				url: "duplicate-merger.php",
				data: {
					'parent_id': parent_id,
					'sub_id': sub_id
				}
			})
			.done(function( msg ) {
				alert( msg );

				//remove the dropped element

				$(ui.draggable).remove();

			});
		}

      }
    });

  });
</script>

</body>
</html>