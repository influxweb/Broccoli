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
	
	if($_SESSION['broccoli_login']=='loggedIn' AND !empty($_SESSION['broccoli_login'])){ //security wrapper
		$id=$_POST['id'];
		//Image delete
		if(isset($_POST['img'])){ //check if image to be deleted
			$dir = str_replace('/ajax','',dirname(__FILE__)).'/img';
			$img = $_POST['img'];
			$img = $dir.'/'.$id.'/'.$img;
			if(!unlink($img)){
				$return='Failed to delete image. Please reload and try again.';
			}else{
				$images = glob('../img/'.$id.'/*.{jpg,gif,png,jpeg,bmp,JPG,GIF,PNG,JPEG,BMP}', GLOB_BRACE);
				$return="<ul>";
				foreach($images as $image){
					$imageName = substr($image, strrpos($image, "/") + 1);
					$return.="<li><span>".$imageName."</span> <strong>&times;</strong></li>";
				}
				$return.="</ul>";
			}
		}
		echo json_encode(array("imgList" => $return));
	}
?>