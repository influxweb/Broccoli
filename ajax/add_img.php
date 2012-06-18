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
		
		$id=$_POST['fileUploadID'];

		$handlers = array('jpg','jpeg','png','gif');
		$extension = strtolower(substr($_FILES['image_upload_file']['name'], strrpos($_FILES['image_upload_file']['name'], '.')+1));
		if(in_array($extension,$handlers)){
			//Image Upload
			if($_FILES['image_upload_file']['tmp_name']!=""){
				$dir = str_replace('/ajax','',dirname(__FILE__)).'/img';
				if(!is_dir($dir)){
					mkdir($dir,0755);
				}
				if(!is_dir($dir.'/'.$id)){
					mkdir($dir.'/'.$id,0755);
				}
				$uploadfile = $dir.'/'.$id.'/'.basename($_FILES['image_upload_file']['name']);
				if(move_uploaded_file($_FILES['image_upload_file']['tmp_name'], $uploadfile)){
					$return ='<div class="alert alert-success">Image uploaded.</div>';
				}else{
					$return = '<div class="alert alert-error">Failed to move file.</div>';
				}
			}else{
				$return = '<div class="alert alert-error">File not found. Please try again.</div>';
			}
		}else{
			$return = '<div class="alert alert-error">File is not a recognised image. Supported image types: jpg, jpeg, png and gif.</div>';
		}
	}
?>
<!doctype html>
<html>
<head>
	<meta charset="UTF-8"/>
</head>
<body>
	<?php echo $return; ?>
	<ul>
		<?php
		$images = glob('../img/'.$id.'/*.{jpg,gif,png,jpeg,bmp,JPG,GIF,PNG,JPEG,BMP}', GLOB_BRACE);
		foreach($images as $image){
			$imageName = substr($image, strrpos($image, "/") + 1);
			echo "<li><span>".$imageName."</span> <strong>&times;</strong></li>";
		} ?>
	</ul>
</body>
</html>