<?php
	session_start();

	include('../db_settings.php');
	//Connect to DB: DO NOT EDIT! //
	$con = mysql_connect($database_host,$database_username,$database_password);
	if(!$con){
		die('Could not connect: ' . mysql_error());
	}else {
		$db = mysql_select_db($database_name);
		if(!$db){
			die('Could not connect to database: ' . mysql_error());
		}
	}
	
	if($_SESSION['broccoli_login']=='loggedIn' AND !empty($_SESSION['broccoli_login'])){
		//FUNCTION: Trim white space and remove empty and duplicate tags when posting to database
		function good_tags($t){
			$good_tags=explode(',',$t);
			foreach($good_tags as $key => $value) {
				$good_tags[$key]=trim($value); //remove white space from either side of tag
				$good_tags[$key]=preg_replace("/[[:blank:]]+/"," ",$good_tags[$key]); //Remove multiple spaces IN tag
				if($good_tags[$key] == "") {
					unset($good_tags[$key]);
				}
			}
			$good_tags=array_unique($good_tags);
			$good_tags=implode(',',$good_tags);
			return $good_tags;
		}
		
		$tag=$_POST['tag'];
		$id=$_POST['id'];
		$tags_query = 'SELECT tags FROM broccoli_posts WHERE id = "'.$id.'"';
		$tag_result = mysql_query($tags_query) or die(mysql_error());
		$current_tags = mysql_fetch_assoc($tag_result);
		$new_tags = good_tags(str_replace($tag,'',$current_tags['tags']));
				
		mysql_query("UPDATE broccoli_posts SET tags = '".$new_tags."' WHERE id = '$id'") or die(mysql_error());
		
		$exploded_tag = explode(',',$new_tags);
		if(!empty($new_tags)){
			foreach($exploded_tag as $tag){
				$return .= '<li><span>'.htmlentities(stripslashes($tag)).'</span> <strong>&times;</strong></li>';
			}
		}else{
			$return = '';
		}
		echo json_encode(array("tagList" => $return));
	}
?>
