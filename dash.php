<?php
session_start();

//Log out
if($_GET['n']=='logout'){
	unset($_SESSION['flameblog_login']);
	unset($_SESSION['flameblog_update']);
	header('Location: dash.php');
}

include('db_settings.php');
//Connect to DB: DO NOT EDIT! //
$con = mysql_connect($flameblog_database_host,$flameblog_database_username,$flameblog_database_password);
if(!$con){
	die('Could not connect: ' . mysql_error());
}else {
	$db = mysql_select_db($flameblog_database_name);
	if(!$db){
		die('Could not connect to database: ' . mysql_error());
	}
}

//Sort out GET variables
if(isset($_GET['id'])){
	$id = mysql_real_escape_string($_GET['id']);
}
if(isset($_GET['page'])){
	$page = mysql_real_escape_string($_GET['page']);
}else{
	$page = 1;
}
if(isset($_GET['tag'])){
	$getTag = mysql_real_escape_string($_GET['tag']);
}

//Get Settings
$settings_query = 'SELECT * FROM flameblog_settings LIMIT 1';
$settings_result = mysql_query($settings_query);
$settings_row = mysql_fetch_assoc($settings_result);

//Process login
if(isset($_POST['pass_submit'])){
	if(sha1(sha1($flameblog_private_key).sha1($_POST['pass_pass']))==$settings_row['pass']){
		$_SESSION['flameblog_login']=md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].$settings_row['pass']); //set session cookie
	} 
}
//Check login
$pass=false;
if($_SESSION['flameblog_login']==md5($_SERVER['HTTP_USER_AGENT'].$_SERVER['REMOTE_ADDR'].$settings_row['pass'])){
	$pass=true;
	if($_SESSION['flameblog_first_login']==true){
		$_SESSION['flameblog_first_login']=false;
	}else{
		$_SESSION['flameblog_first_login']=true;
	}
}else{
	$pass=false;
	unset($_SESSION['flameblog_login']);
}

//FUNCTION: Trim white space and remove empty and duplicate tags when posting to database
function good_tags($tags){
	$good_tags=explode(',',$tags);
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

///////////Deal with POST variables/////////////////////////////////////////////////////////////////////////////////////
	//Settings
	if(isset($_POST['setup_submit'])){
		mysql_query("UPDATE flameblog_settings SET email = '".trim(mysql_real_escape_string($_POST['setup_email']))."', per_page = '".trim(mysql_real_escape_string($_POST['setup_per_page']))."' WHERE id = 1") or die(mysql_error());
		$alert_message.='<div class="alert-message info fade in" data-alert="alert">Blog settings saved</div>';
		$settings_query = 'SELECT * FROM flameblog_settings LIMIT 1';
		$settings_result = mysql_query($settings_query);
		$settings_row = mysql_fetch_assoc($settings_result);
	}

	//Update
	if(isset($_POST['edit_submit'])){
		$good_tags = addslashes(good_tags($_POST[edit_tags]));
		mysql_query("UPDATE flameblog_posts SET title = '".addslashes($_POST[edit_title])."', content = '".addslashes($_POST[edit_content])."', tags = '$good_tags' WHERE id = '$id'") or die(mysql_error());
		$alert_message.='<div class="alert-message info fade in" data-alert="alert">Post saved</div>';
	}

	//Image Upload
	if(isset($_POST['image_upload_submit'])){
		if($_FILES['image_upload_file']['tmp_name']!=""){
			$dir = dirname(__FILE__).'/img';
			if(!is_dir($dir)){
				mkdir($dir,0755);
			}
			if(!is_dir($dir.'/'.$id)){
				mkdir($dir.'/'.$id,0755);
			}
			$uploadfile = $dir.'/'.$id.'/'.basename($_FILES['image_upload_file']['name']);
			if(move_uploaded_file($_FILES['image_upload_file']['tmp_name'], $uploadfile)){
				$alert_message.='<div class="alert-message info fade in" data-alert="alert">Image uploaded</div>';
			}else{
				$alert_message.='<div class="alert-message danger fade in" data-alert="alert">Image failed to upload. Please try again.</div>';
			}
		}
	}
	//Image delete
	if(isset($_POST['image_delete_submit'])){ //check if image to be deleted
		$file = $_POST['image_delete'];
		$file = dirname(__FILE__).'/img/'.$id.'/'.$file;
		if(!unlink($file)){
			$flameblog_alert_message.='<div class="alert-message danger fade in" data-alert="alert">Failed to delete image. Please try again.</div>';
		}else{
			$flameblog_alert_message.='<div class="alert-message info fade in" data-alert="alert">Image Deleted</div>';
		}
	}
	//Publish
	if(isset($_POST['pub_submit'])){
		if($_POST['pubImm']=='yes'){
			$time_stamp = date("Y-m-d H:i:s");
		}else{
			$time_stamp = $_POST['pub_year'].'-'.$_POST['pub_month'].'-'.$_POST['pub_day'].' '.$_POST['pub_hour'].':'.$_POST['pub_minute'];
		}
		mysql_query("UPDATE flameblog_posts SET date= '$time_stamp', published = '1' WHERE id = '$id'") or die(mysql_error());
		$flameblog_alert_message.='<div class="alert-message success fade in" data-alert="alert">Published</div>';
	}
	//Unpublish
	if(isset($_POST['unpub_submit'])){
		mysql_query("UPDATE flameblog_posts SET published = '0' WHERE id = '$id'") or die(mysql_error());
		$flameblog_alert_message.='<div class="alert-message danger fade in" data-alert="alert">Unpublished</div>';
	}
	//Delete
	if(isset($_POST['delete_submit'])){
		mysql_query("DELETE FROM flameblog_posts WHERE id = $id") or die(mysql_error());
		$dir = dirname(__FILE__).'/img/'.$id;
		if(is_dir($dir)){
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object!="." && $object!=".."){
					unlink($dir."/".$object);
				}
			}
		reset($objects);
		rmdir($dir);
		}
		header('Location: dash.php');
	}
	//Add
	if(isset($_GET['add'])){
		mysql_query("INSERT INTO flameblog_posts (title, content, tags) VALUES ('', '', '')") or die(mysql_error());
		$next_inc_value = mysql_insert_id();
		header('Location: dash.php?n=edit&id='.$next_inc_value);
	}
	//Image delete
	if(isset($_POST['flameblog_image_delete_submit'])){ //check if image to be deleted
		$file = $_POST['flameblog_image_delete'];
		$file = dirname(__FILE__).'/img/'.$flameblog_id.'/'.$file;
		if (!unlink($file)){
			$flameblog_alert_message.='<div class="alert-message danger fade in" data-alert="alert">Failed to delete image. Please try again.</div>';
		}
		else{
			$flameblog_alert_message.='<div class="alert-message info fade in" data-alert="alert">Image Deleted</div>';
		}
	}
///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

//QUERY (dependent on situation)
	if(isset($id)){ //individual posts
		$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM flameblog_posts WHERE id = "'.$id.'"';
	}else{ //lists
		$limit = (($page*20)-20).',20'; //for limiting the results brought back for pagination
		$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM flameblog_posts WHERE tags LIKE "%'.$getTag.'%" ORDER BY date DESC LIMIT '.$limit;
		$total_query = 'SELECT count(id) as total FROM flameblog_posts WHERE published = "1" AND tags LIKE "%'.$getTag.'%"';
		$total_result = mysql_query($total_query) or die(mysql_error());
		$total_row = mysql_fetch_assoc($total_result);
		$max_pages = ceil($total_row['total']/20);
	}
	$post_result = mysql_query($post_query) or die(mysql_error());
	//prev next
	if(isset($id)){
		$post_row = mysql_fetch_assoc($post_result);
		$previousID = mysql_fetch_row(mysql_query('SELECT id FROM flameblog_posts WHERE UNIX_TIMESTAMP(date)<'.$post_row['date'].' ORDER BY date DESC LIMIT 1'));
		$previousID = $previousID[0];
		$nextID = mysql_fetch_row(mysql_query('SELECT id FROM flameblog_posts WHERE UNIX_TIMESTAMP(date)>'.$post_row['date'].' ORDER BY date ASC LIMIT 1'));
		$nextID = $nextID[0];
		mysql_data_seek($post_result,0);
	}
//END QUERY
?>
<!doctype html>
<html>
<head>
	<meta charset="UTF-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>FlameBlog Dashboard</title>
	<style type="text/css">
		body {
			padding-top: 40px;
			padding-bottom: 40px;
		}
    </style>
	<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"/>
	<link rel="stylesheet" type="text/css" href="css/bootstrap-responsive.min.css"/>
	<link rel="stylesheet" type="text/css" href="js/markitup/skins/simple/style.css" />
	<link rel="stylesheet" type="text/css" href="js/markitup/sets/html/style.css" />
	<link rel="stylesheet" type="text/css" href="css/dash_tweaks.css" />
	<script type="text/javascript" src="js/jquery-1.7.2.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script type="text/javascript" src="js/markitup/jquery.markitup.js"></script>
	<script type="text/javascript" src="js/markitup/sets/html/set.js"></script>
	<script>
		//Markitup settings
		$(document).ready(function() {
			$("#edit_content").markItUp(mySettings);
			//Hide div w/id extra
			$("#pubDateControls").css("display","none");
			$("#pubImm").attr("autocomplete", "off");
			// Add onclick handler to checkbox w/id checkme
			$("#pubImm").click(function(){
				// If checked
				if ($("#pubImm").is(":checked")){
					//show the hidden div
					$("#pubDateControls").hide("fast");
				}
				else{
					//otherwise, hide it
					$("#pubDateControls").show("fast");
				}
			});
		});
	</script>
</head>
<body>
	<?php
	if($pass!=true){ ?>
			<form class="well form-inline" action="dash.php" method="post" id="pass_form" name="pass_form" style="width:320px; text-align:center; margin:20px auto;">
				<input type="password" placeholder="Password" id="pass_pass" name="pass_pass">
				<button type="submit" class="btn" name="pass_submit" id="pass_submit">Submit</button>
			</form>
		<?php
	}else{ ?>
		<div class="navbar navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container-fluid">
					<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					</a>
					<a class="brand" href="dash.php">FlameBlog Dashboard</a>
					<div class="nav-collapse">
						<ul class="nav">
							<li<?php if(empty($_GET[n])){echo ' class="active"';} ?>><a href="dash.php"><i class="icon-align-justify icon-white"></i> Posts</a></li>
							<li><a href="dash.php?add=yes"><i class="icon-plus icon-white"></i> New</a></li>
							<li<?php if($_GET[n]=='settings'){echo ' class="active"';} ?>><a href="dash.php?n=settings"><i class="icon-cog icon-white"></i> Settings</a></li>
						</ul>
						<p class="navbar-text pull-right"><a href="dash.php?n=logout"><i class="icon-off icon-white"></i> Log Out</a></p>
					</div><!--/.nav-collapse -->
				</div>
			</div>
		</div>

		<div class="container">
			<!--Body content-->
			<?php // LIST POST
			if(empty($_GET[n])){ ?>
				<div class="page-header">
					<h1>Posts</h1>
				</div>
				<table class="table" id="postList">
					<tr>
						<th>ID</th>
						<th>Title</th>
						<th>Tags</th>
						<th>Date</th>
						<th>Status</th>
						<th>Heat</th>
					</tr>
					<?php
					while($post_row = mysql_fetch_assoc($post_result)){ ?>
						<tr>
							<td><?php echo $post_row['id']; ?></td>
							<td><a href="dash.php?n=edit&amp;id=<?php echo $post_row['id']; ?>"><?php echo htmlspecialchars($post_row['title']); ?> <i class="icon-pencil"></i></a></td>
							<td><?php $exploded_tag = explode(',',$post_row['tags']); $numTags=count($exploded_tag); $i=1; foreach($exploded_tag as $tag){ ?><a href="dash.php?tag=<?php echo $tag; ?>"><?php echo htmlspecialchars($tag); ?></a><?php if($i!=$numTags){echo ', ';} $i++; } ?></td>
							<td><?php echo date('H:i, d-m-Y',$post_row['date']); ?></td>
							<td><?php if($post_row['published']==1 AND $post_row['date']>time()){echo ' <span class="label label-warning">Scheduled</warning>';}elseif($post_row['published']==1){echo ' <span class="label label-success">Published</span>';}else{echo ' <span class="label label-info">Draft</span>';} ?></td>
							<td><?php echo $post_row['heat']; ?></td>
						</tr>
						<?php
					} ?>
				</table>
				<div class="pagination pagination-centered">
				<ul>
					<li class="disabled"><a href="#">&larr;</a></li>
					<li class="active"><a href="#">1</a></li>
					<li><a href="#">2</a></li>
					<li><a href="#">3</a></li>
					<li><a href="#">4</a></li>
					<li><a href="#">&rarr;</a></li>
				</ul>
				</div>
				<?php
			// EDIT POST
			}elseif($_GET[n]=='edit' AND !empty($id)){
				$post_row = mysql_fetch_assoc($post_result);
				if(!empty($post_row)){ ?>
					<div class="page-header">
						<h1>Edit Post</h1>
					</div>
					<div class="row">
						<div class="span3">
							<div class="well well-small">
								<?php
								if($post_row['published']==1 AND $post_row['date']>time()){
									echo ' <div class="alert alert-warning">Scheduled: '.date('H:i, d-m-Y',$post_row['date']).'</div>';
								}elseif($post_row['published']==1){
									echo ' <div class="alert alert-success">Published: '.date('H:i, d-m-Y',$post_row['date']).'</div>';
								}else{
									echo ' <div class="alert alert-info">Draft</div>';
								}
								if($post_row['published']==0){ ?>
									<form action="dash.php?n=edit&amp;id=<?php echo $id; ?>" method="post" id="pub_form" name="pub_form">
										<fieldset>
											<label class="checkbox inline">
												<input id="pubImm" name="pubImm" type="checkbox" value="yes" checked>
												Publish now
											</label>
											<div class="control-group" id="pubDateControls">
												<div class="controls">
													<div style="display:inline-block;">
														<input type="text" id="pub_day" name="pub_day" style="width:15px;" maxlength="2" value="<?php echo date('d'); ?>">-<input type="text" id="pub_month" name="pub_month" style="width:15px;" maxlength="2" value="<?php echo date('m'); ?>">-<input type="text" id="pub_year" name="pub_year" style="width:30px;" maxlength="4" value="<?php echo date('Y'); ?>">
														<p class="help-block">DD-MM-YYYY</p>
													</div>
													<div style="display:inline-block; vertical-align:top;">
													@
													</div>
													<div style="display:inline-block;">
														<input type="text" id="pub_hour" name="pub_hour" style="width:15px;" maxlength="2" value="<?php echo date('H'); ?>">:<input type="text" id="pub_minute" name="pub_minute" style="width:15px;" maxlength="2" value="<?php echo date('i'); ?>">
														<p class="help-block">HH:MM</p>
													</div>
												</div>
											</div>
											<button class="btn btn-primary" name="pub_submit" type="submit">Publish</button>
										</fieldset>
									</form>
									<?php
								}elseif($post_row['published']==1){ ?>
									<form class="form-inline" action="dash.php?n=edit&amp;id=<?php echo $id; ?>" method="post" id="unpub_form" name="unpub_form">
										<fieldset>
											<button class="btn btn-warning" name="unpub_submit" type="submit">Unpublish</button>
										</fieldset>
									</form>
									<?php
								}else{echo 'Database corruption in the Publish field. e.g. it is NULL, non-numeric or not a valid number.';} ?>
								<form class="form-inline" action="dash.php?n=edit&amp;id=<?php echo $id; ?>" method="post" id="delete_form" name="delete_form">
									<fieldset>
										<button class="btn btn-danger" name="delete_submit" type="submit" onclick="return (confirm('Do you really want to delete this post\nand any images associated with it?'));"><i class="icon-trash icon-white"></i> Delete</button>
									</fieldset>
								</form>
							</div>
						</div>
						<div class="span9">
							<ul class="nav nav-tabs">
								<li<?php if($_GET['t']=='preview' OR empty($_GET['t'])){echo ' class="active"';} ?>><a href="#previewTab" data-toggle="tab">Preview</a></li>
								<li<?php if($_GET['t']=='edit'){echo ' class="active"';} ?>><a href="#editFormTab" data-toggle="tab">Edit</a></li>
								<li<?php if($_GET['t']=='media'){echo ' class="active"';} ?>><a href="#mediaTab" data-toggle="tab">Attached Media</a></li>
							</ul>
							<div class="tab-content">
								<!-- PREVIEW TAB -->
								<div class="tab-pane<?php if($_GET['t']=='preview' OR empty($_GET['t'])){echo ' active';} ?>" id="previewTab">
									<?php echo str_replace("&","&amp;",$post_row[content]); ?>
								</div>
								<!-- EDIT TAB -->
								<div class="tab-pane<?php if($_GET['t']=='edit'){echo ' active';} ?>" id="editFormTab">
									<form action="dash.php?n=edit&amp;id=<?php echo $id; ?>&amp;t=edit" method="post" name="edit_form">
										<fieldset>
											<div class="control-group">
												<label class="control-label" for="edit_title">Title</label>
												<div class="controls">
													<input type="text" id="edit_title" name="edit_title"<?php if(!empty($post_row['title'])){echo ' value="'.htmlspecialchars($post_row['title']).'"';} ?>>
												</div>
											</div>
											<div class="control-group">
												<label class="control-label" for="edit_content">Content</label>
												<div class="controls">
													<textarea rows="10" name="edit_content" id="edit_content"><?php echo $post_row[content]; ?></textarea>
												</div>
											</div>
											<div class="control-group">
												<label class="control-label" for="edit_tags">Tags</label>
												<div class="controls">
													<input type="text" id="edit_tags" name="edit_tags"<?php if(!empty($post_row['tags'])){echo ' value="'.htmlspecialchars($post_row['tags']).'"';} ?>>
												</div>
											</div>
											<div class="form-actions">
												<button class="btn btn-primary" name="edit_submit" type="submit">Save changes</button>
											</div>
										</fieldset>
									</form>
								</div>
								<!-- MEDIA TAB -->
								<div class="tab-pane<?php if($_GET['t']=='media'){echo ' active';} ?>" id="mediaTab">
									<?php
									$images = glob('img/'.$id.'/*.{jpg,gif,png,jpeg,bmp,JPG,GIF,PNG,JPEG,BMP}', GLOB_BRACE); ?>
									<div class="attachedImages">
										<ul class="thumbnails">
										<?php
										foreach($images as $image){
											$imageName = substr($image, strrpos($image, "/") + 1); ?>
											<li>
												<div class="thumbnail">
													<img alt="image" src="<?php echo $image; ?>">
													<div class="caption" style="text-align:center;">
														<h5 style="margin-bottom:10px;"><?php echo dirname($_SERVER['PHP_SELF']); ?>/<?php echo $image; ?></h5>
														<form action="dash.php?n=edit&amp;id=<?php echo $id; ?>&amp;t=media" method="post" name="image_delete_form" style="margin-bottom:0;">
															<input type="hidden" name="image_delete" value="<?php echo $imageName ; ?>"/>
															<button class="btn btn-danger" name="image_delete_submit" type="submit" onclick="return(confirm('Do you really want to delete <?php echo $imageName; ?>?'));">Delete</button>
														</form>
													</div>
												</div>
											</li>
											<?php 
										} ?>
										</ul>
									</div>
									<form action="dash.php?n=edit&amp;id=<?php echo $id; ?>&amp;t=media" method="post" name="image_upload_form" class="well form-inline" enctype="multipart/form-data">
										<fieldset>
											<input type="hidden" name="MAX_FILE_SIZE" value="51200000"/>
											<input type="file" name="image_upload_file"/>
											<button class="btn btn-primary" name="image_upload_submit" type="submit">Upload</button>
										</fieldset>
									</form>
								</div>
							</div>
						</div>
					</div>
					<?php
				}else{
					echo '<div>Post ID does not exist.</div>';
				}
			// SETTINGS
			}elseif($_GET[n]=='settings'){ ?>
				
				<div class="page-header">
					<h1>Settings</h1>
				</div>
				
				<form class="form-horizontal" action="dash.php?n=settings" method="post" name="setup_form">
					<fieldset>
						<legend>Blog Setup</legend>
						<div class="control-group">
							<label class="control-label" for="setup_email">Your email</label>
							<div class="controls">
								<input type="text" id="setup_email" name="setup_email"<?php if(!empty($settings_row['email'])){echo ' value="'.$settings_row['email'].'"';} ?>>
							</div>
						</div>
						<div class="control-group">
							<label class="control-label" for="setup_per_page">Posts per page</label>
							<div class="controls">
								<input type="text" id="setup_per_page" name="setup_per_page"<?php if(!empty($settings_row['per_page'])){echo ' value="'.$settings_row['per_page'].'"';} ?>>
							</div>
						</div>
						<div class="form-actions">
							<button class="btn btn-primary" name="setup_submit" type="submit">Save changes</button>
						</div>
					</fieldset>
				</form>
				<form class="form-horizontal" action="dash.php?n=settings" method="post" name="pass_form">
					<fieldset>
						<legend>Change Password</legend>
						<div class="control-group">
							<label class="control-label" for="pass_old">Old password</label>
							<div class="controls">
								<input type="text" id="pass_old" name="pass_old">
							</div>
						</div>
						<div class="control-group">
							<label class="control-label" for="pass_new">New password</label>
							<div class="controls">
								<input type="text" id="pass_new" name="pass_new">
							</div>
						</div>
						<div class="control-group">
							<label class="control-label" for="pass_verify">Verify new password</label>
							<div class="controls">
								<input type="text" id="pass_verify" name="pass_verify">
							</div>
						</div>
						<div class="form-actions">
							<button class="btn btn-primary" name="pass_submit" type="submit">Change password</button>
						</div>
					</fieldset>
				</form>
				<?php
			} ?>
		</div>
		<?php
	} ?>
</body>
</html>
