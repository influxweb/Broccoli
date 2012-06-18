<?php
session_start();

//Log out
if($_GET['n']=='logout'){
	unset($_SESSION['broccoli_login']);
	unset($_SESSION['broccoli_update']);
	header('Location: index.php');
}

include('db_settings.php');
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

//Get settings and setup database
	//create posts table if doesn't exist
	mysql_query("CREATE TABLE IF NOT EXISTS broccoli_posts (id int(5) NOT NULL auto_increment,date timestamp NOT NULL default CURRENT_TIMESTAMP,title text collate latin1_general_ci NOT NULL,content text collate latin1_general_ci NOT NULL,tags text collate latin1_general_ci,published int(1) NOT NULL default '0',heat int(11) NOT NULL default '0',PRIMARY KEY  (id)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1");
	//create settings table if doesn't exist
	mysql_query("CREATE TABLE IF NOT EXISTS broccoli_settings (id int(1) NOT NULL AUTO_INCREMENT,pass varchar(64) NOT NULL,email varchar(64) NOT NULL,per_page int(2) NOT NULL DEFAULT '5',PRIMARY KEY (id)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2");
	mysql_query("INSERT INTO broccoli_settings (id, pass, email, per_page) VALUES (1, '', '', 3)");

//Enter first password
if(isset($_POST['pass_first_submit'])){
	if($_POST['pass_first']==$_POST['pass_first_verify']){
		mysql_query("UPDATE broccoli_settings SET pass = '".hash('sha256',$_POST['pass_first'])."' WHERE id = '1'") or die(mysql_error());
		$_SESSION['broccoli_login']='loggedIn';
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
$settings_query = 'SELECT * FROM broccoli_settings LIMIT 1';
$settings_result = mysql_query($settings_query);
$settings_row = mysql_fetch_assoc($settings_result);

//Process login
if(isset($_POST['pass_submit'])){
	if(hash('sha256',$_POST['pass_pass'])==$settings_row['pass']){
		$_SESSION['broccoli_login']='loggedIn'; //set session cookie
	} 
}

//Check login
$pass=false;
if($_SESSION['broccoli_login']=='loggedIn' AND !empty($_SESSION['broccoli_login'])){
	$pass=true;
	if($_SESSION['broccoli_first_login']==true){
		$_SESSION['broccoli_first_login']=false;
	}else{
		$_SESSION['broccoli_first_login']=true;
	}
}else{
	$pass=false;
	unset($_SESSION['broccoli_login']);
}

if($pass==true){ //security wrapper
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
			mysql_query("UPDATE broccoli_settings SET email = '".trim(mysql_real_escape_string($_POST['setup_email']))."', per_page = '".trim(mysql_real_escape_string($_POST['setup_per_page']))."' WHERE id = 1") or die(mysql_error());
			$alert_message.='<div class="alert-message info fade in" data-alert="alert">Blog settings saved</div>';
			$settings_query = 'SELECT * FROM broccoli_settings LIMIT 1';
			$settings_result = mysql_query($settings_query);
			$settings_row = mysql_fetch_assoc($settings_result);
		}

		//Update
		if(isset($_POST['save_submit'])){
			//$good_tags = addslashes(good_tags($_POST[edit_tags]));
			mysql_query("UPDATE broccoli_posts SET title = '".addslashes($_POST[edit_title])."', content = '".addslashes($_POST[edit_content])."' WHERE id = '$id'") or die(mysql_error());
			$alert_message.='<div class="alert-message info fade in" data-alert="alert">Post saved</div>';
		}
		
		//Publish
		if(isset($_POST['pub_submit'])){
			if($_POST['pubImm']=='yes'){
				$time_stamp = date("Y-m-d H:i:s");
			}else{
				$time_stamp = $_POST['pub_year'].'-'.$_POST['pub_month'].'-'.$_POST['pub_day'].' '.$_POST['pub_hour'].':'.$_POST['pub_minute'];
			}
			mysql_query("UPDATE broccoli_posts SET title = '".addslashes($_POST[edit_title])."', content = '".addslashes($_POST[edit_content])."', date= '$time_stamp', published = '1' WHERE id = '$id'") or die(mysql_error());
			$broccoli_alert_message.='<div class="alert-message success fade in" data-alert="alert">Published</div>';
		}
		//Unpublish
		if(isset($_POST['unpub_submit'])){
			mysql_query("UPDATE broccoli_posts SET published = '0' WHERE id = '$id'") or die(mysql_error());
			$broccoli_alert_message.='<div class="alert-message danger fade in" data-alert="alert">Unpublished</div>';
		}
		//Delete
		if(isset($_POST['delete_submit'])){
			mysql_query("DELETE FROM broccoli_posts WHERE id = $id") or die(mysql_error());
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
			header('Location: index.php');
		}
		//Add
		if(isset($_GET['add'])){
			mysql_query("INSERT INTO broccoli_posts (title, content, tags) VALUES ('', '', '')") or die(mysql_error());
			$next_inc_value = mysql_insert_id();
			header('Location: index.php?n=edit&id='.$next_inc_value.'&t=edit');
		}
	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	//QUERY (dependent on situation)
		if(isset($id)){ //individual posts
			$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM broccoli_posts WHERE id = "'.$id.'"';
		}else{ //lists
			$limit = (($page*30)-30).',30'; //for limiting the results brought back for pagination
			if($_GET['f']=='all' OR empty($_GET['f'])){
				$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM broccoli_posts WHERE tags LIKE "%'.$getTag.'%" ORDER BY date DESC LIMIT '.$limit;
			}elseif($_GET['f']=='published'){
				$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM broccoli_posts WHERE tags LIKE "%'.$getTag.'%" AND published="1" AND date < now() ORDER BY date DESC LIMIT '.$limit;
			}elseif($_GET['f']=='scheduled'){
				$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM broccoli_posts WHERE tags LIKE "%'.$getTag.'%" AND published="1" AND date > now() ORDER BY date DESC LIMIT '.$limit;
			}elseif($_GET['f']=='draft'){
				$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM broccoli_posts WHERE tags LIKE "%'.$getTag.'%" AND published="0" ORDER BY date DESC LIMIT '.$limit;
			}
			$total_query = 'SELECT count(id) as total FROM broccoli_posts WHERE published = "1" AND tags LIKE "%'.$getTag.'%"';
			$total_result = mysql_query($total_query) or die(mysql_error());
			$total_row = mysql_fetch_assoc($total_result);
			$max_pages = ceil($total_row['total']/30);
		}
		$post_result = mysql_query($post_query) or die(mysql_error());
	//END QUERY
}//end security wrapper
?>
<!doctype html>
<html>
<head>
	<meta charset="UTF-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Broccoli Dashboard</title>
	<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"/>
	<style>
		body{
			padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
		}
    </style>
	<link rel="stylesheet" type="text/css" href="css/bootstrap-responsive.min.css"/>
	<link rel="stylesheet" type="text/css" href="css/dash_tweaks.css" />
	<link rel="icon" href="favicon.ico" type="image/x-icon">
	<script type="text/javascript" src="js/jquery-1.7.2.min.js"></script>
	<script src="js/bootstrap.min.js"></script>
	<script type="text/javascript" src="js/tiny_mce/tiny_mce.js"></script >
	<script type="text/javascript">
		tinyMCE.init({
			mode : "textareas",
			convert_urls : false,
			relative_urls : false,
			theme : "advanced",
			plugins : "spellchecker,advhr,insertdatetime",
			// Theme options - button# indicated the row# only
			theme_advanced_buttons1 : "undo,redo,fontselect,fontsizeselect,formatselect",
			theme_advanced_buttons2 : "bold,italic,underline,|,justifyleft,justifycenter,justifyright,|,bullist,numlist,outdent,indent,forecolor,backcolor",
			theme_advanced_buttons3 : "link,unlink,anchor,image,|,code,insertdate,inserttime,|,spellchecker,advhr,,removeformat,|,sub,sup",      
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",
			theme_advanced_statusbar_location : "bottom",
			theme_advanced_resizing : true
		});
	</script>
	<script type="text/javascript">
		$(document).ready(function(){
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
			$('.filterRadio').click(function(){
				$('#filter_form').submit();
			});
		});
		//tag AJAX
		$(document).ready(function(){
			//Insert image
			var insertImage = function(){
				$('#imageList li span').click(function(){
					var src = $(this).text();
					if(src != '') {
						var image = '<img src="/broccoli/img/<?php echo $id; ?>/' + src + '" alt=""/>';
						tinyMCE.execCommand('mceInsertContent', false, image);
					}
				});
			};
			insertImage();
			var deleteTag=function(){ //deleteTag
				$('#tagList li strong').click(function(){
					var tag = $(this).prev('span').text();
					var id = "<?php echo $id; ?>";
					if (tag == ""){  
						return false;
					}
					$.post("ajax/del_tag.php",{
						tag: tag,
						id: id
					},function(a){
						$("#tagList").html(a.tagList);
						deleteTag();
					},"json");
					return false;
				});
			}
			deleteTag();
			
			$("#add_tag").click(function(){ //Add tag
				$("#tagList").html("Loading...");
				var tags = $("input#edit_tags").val();
				var id = "<?php echo $id; ?>";
				if (tags == ""){  
					return false;
				}
				$.post("ajax/add_tag.php",{
					tags: tags,
					id: id
				},function(a){
					$("#tagList").html(a.tagList);
					$("input#edit_tags").val('');
					deleteTag();
				},"json");
				return false;
			});
		
			//image AJAX
			var deleteImg=function(){
				$("#imageList ul li strong").click(function(){
					var img = $(this).prev('span').text();
					var id = "<?php echo $id; ?>";
					if (img == ""){
						return false;
					}
					$.post("ajax/del_img.php",{
						img: img,
						id: id
					},function(a){
						$("#imageList").html(a.imgList);
						deleteImg();
						insertImage();
					},"json");
					return false;
				});
			}
			deleteImg();
			
			$("#image_upload_file_button").click(function(){
				// Create the iframe...
				var iframe = document.createElement("iframe");
				var form = this.form;
				var action_url = 'ajax/add_img.php';
				var div_id = 'upload';
				iframe.setAttribute("id", "upload_iframe");
				iframe.setAttribute("name", "upload_iframe");
				iframe.setAttribute("width", "0");
				iframe.setAttribute("height", "0");
				iframe.setAttribute("border", "0");
				iframe.setAttribute("style", "width: 0; height: 0; border: none;");
				// Add to document...
				form.parentNode.appendChild(iframe);
				window.frames['upload_iframe'].name = "upload_iframe";
				iframeId = document.getElementById("upload_iframe");
				// Add event...
				var eventHandler = function () {
					if (iframeId.detachEvent) iframeId.detachEvent("onload", eventHandler);
					else iframeId.removeEventListener("load", eventHandler, false);
					// Message from server...
					if (iframeId.contentDocument) {
						content = iframeId.contentDocument.body.innerHTML;
					} else if (iframeId.contentWindow) {
						content = iframeId.contentWindow.document.body.innerHTML;
					} else if (iframeId.document) {
						content = iframeId.document.body.innerHTML;
					}
					$("#imageList").html(content);
					deleteImg();
					insertImage();
					// Del the iframe...
					setTimeout('iframeId.parentNode.removeChild(iframeId)', 250);
				}
				if (iframeId.addEventListener) iframeId.addEventListener("load", eventHandler, true);
				if (iframeId.attachEvent) iframeId.attachEvent("onload", eventHandler);
				// Set properties of form...
				form.setAttribute("target", "upload_iframe");
				form.setAttribute("action", action_url);
				form.setAttribute("method", "post");
				form.setAttribute("enctype", "multipart/form-data");
				form.setAttribute("encoding", "multipart/form-data");
				// Submit the form...
				form.submit();
				$("#imageList").html("Uploading...");
				return false;
			});		
		});
	</script>
</head>
<body>
	<?php
	if(empty($settings_row['pass'])){ ?>
		<div class="container">
			<form class="well form-horizontal" action="index.php" method="post" name="pass_first_form">
				<fieldset>
					<legend>Enter a Password</legend>
					<div class="control-group">
						<label class="control-label" for="pass_first">Password</label>
						<div class="controls">
							<input type="password" id="pass_first" name="pass_first">
						</div>
					</div>
					<div class="control-group">
						<label class="control-label" for="pass_first_verify">Verify password</label>
						<div class="controls">
							<input type="password" id="pass_first_verify" name="pass_first_verify">
						</div>
					</div>
					<div class="form-actions">
						<button class="btn btn-primary" name="pass_first_submit" type="submit">Change password</button>
					</div>
				</fieldset>
			</form>
		</div>
		<?php
	}elseif($pass!=true){ ?>
			<form class="well form-inline" action="index.php" method="post" id="pass_form" name="pass_form" style="width:320px; text-align:center; margin:20px auto;">
				<input type="password" placeholder="Password" id="pass_pass" name="pass_pass">
				<button type="submit" class="btn" name="pass_submit" id="pass_submit">Submit</button>
			</form>
		<?php
	}else{ ?>
		<div class="navbar navbar-fixed-top">
			<div class="navbar-inner">
				<div class="container">
					<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					</a>
					<a class="brand" href="index.php">B<sup>roccoli</sup></a>
					<div class="nav-collapse collapse">
						<ul class="nav">
							<li<?php if(empty($_GET[n])){echo ' class="active"';} ?>><a href="index.php"><i class="icon-align-justify icon-white"></i> Posts</a></li>
							<li><a href="index.php?add=yes"><i class="icon-plus icon-white"></i> New</a></li>
							<li<?php if($_GET[n]=='settings'){echo ' class="active"';} ?>><a href="index.php?n=settings"><i class="icon-cog icon-white"></i> Settings</a></li>
						</ul>
						<p class="navbar-text pull-right"><a href="index.php?n=logout"><i class="icon-off icon-white"></i> Log Out</a></p>
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
					<h4><?php if(isset($getTag)){echo 'Tag: '.$getTag;} ?></h4>
				</div>
				<form class="form-inline" id="filter_form" action="index.php" method="get">
					<fieldset>
						<div class="controls">
							<label class="radio inline">
								<input id="publishedOptionsRadios1" type="radio" <?php if($_GET['f']=='all' OR empty($_GET['f'])){echo 'checked="" '; } ?>value="all" name="f" class="filterRadio"/>
								All
							</label>
							<label class="radio inline">
								<input id="publishedOptionsRadios2" type="radio" <?php if($_GET['f']=='published'){echo 'checked="" '; } ?>value="published" name="f" class="filterRadio"/>
								Published
							</label>
							<label class="radio inline">
								<input id="publishedOptionsRadios3" type="radio" <?php if($_GET['f']=='scheduled'){echo 'checked="" '; } ?>value="scheduled" name="f" class="filterRadio"/>
								Scheduled
							</label>
							<label class="radio inline">
								<input id="publishedOptionsRadios4" type="radio" <?php if($_GET['f']=='draft'){echo 'checked="" '; } ?>value="draft" name="f" class="filterRadio"/>
								Draft
							</label>
							<?php if(isset($getTag)){echo '<input type="hidden" name="tag" value="'.$getTag.'">';} ?>
						</div>
					</fieldset>
				</form>
				<table class="table" id="postList">
					<tr>
						<th class="hidden-phone">ID</th>
						<th>Title</th>
						<th class="hidden-phone">Tags</th>
						<th class="hidden-phone">Date</th>
						<th>Status</th>
						<th class="hidden-phone">Heat</th>
					</tr>
					<?php
					while($post_row = mysql_fetch_assoc($post_result)){ ?>
						<tr>
							<td class="hidden-phone"><?php echo $post_row['id']; ?></td>
							<td><a href="index.php?n=edit&amp;id=<?php echo $post_row['id']; ?>"><?php echo htmlspecialchars($post_row['title']); ?> <i class="icon-pencil"></i></a></td>
							<td class="hidden-phone"><?php $exploded_tag = explode(',',$post_row['tags']); $numTags=count($exploded_tag); $i=1; foreach($exploded_tag as $tag){ ?><a href="index.php?tag=<?php echo urlencode($tag); ?>"><?php echo htmlspecialchars($tag); ?></a><?php if($i!=$numTags){echo ', ';} $i++; } ?></td>
							<td class="hidden-phone"><?php echo date('H:i, d-m-Y',$post_row['date']); ?></td>
							<td><?php if($post_row['published']==1 AND $post_row['date']>time()){echo ' <span class="label label-warning">Scheduled</warning>';}elseif($post_row['published']==1){echo ' <span class="label label-success">Published</span>';}else{echo ' <span class="label label-info">Draft</span>';} ?></td>
							<td class="hidden-phone"><?php echo $post_row['heat']; ?></td>
						</tr>
						<?php
					} ?>
				</table>
				<div class="pagination pagination-centered">
					<ul>
						<li <?php if($page==1){echo 'class="disabled"';} ?>>
							<?php
							if($page!=1){ ?>
								<a href="index.php?page=<?php echo $page-1; if(isset($getTag)){echo '&amp;tag='.$getTag;} ?>">&larr;</a>
								<?php
							}else{ ?>
								<a href="#">&larr;</a>
								<?php
							} ?>
						</li>
						<li class="disabled"><a href="#">Page <?php echo $page; ?> of <?php echo $max_pages; ?></a></li>
						<li <?php if($page==$max_pages){echo 'class="disabled"';} ?>>
							<?php
							if($page!=$max_pages){ ?>
								<a href="index.php?page=<?php echo $page+1; if(isset($getTag)){echo '&amp;tag='.$getTag;} ?>">&rarr;</a>
								<?php
							}else{ ?>
								<a href="#">&rarr;</a>
								<?php
							} ?>
						</li>
					</ul>
				</div>
				<?php
			// EDIT POST
			}elseif($_GET[n]=='edit' AND !empty($id)){
				$post_row = mysql_fetch_assoc($post_result);
				if(!empty($post_row)){ ?>
					<div class="row">
						<form action="index.php?n=edit&amp;id=<?php echo $id; ?>" method="post" name="edit_form" id="edit_form">
							<div class="span9">
								<fieldset>
									<div class="control-group">
										<label class="control-label" for="edit_title">Title</label>
										<input type="text" class="span9" id="edit_title" name="edit_title"<?php if(!empty($post_row['title'])){echo ' value="'.htmlspecialchars($post_row['title']).'"';} ?>>
									</div>
									<div class="control-group">
										<label class="control-label" for="edit_content">Content</label>
										<div class="controls">
											<textarea rows="20" class="span9" name="edit_content" id="edit_content"><?php echo $post_row[content]; ?></textarea>
										</div>
									</div>
								</fieldset>
							</div>
							<div class="span3">
								<?php
								if($post_row['published']==1 AND $post_row['date']>time()){
									echo ' <div class="alert alert-warning">Scheduled: '.date('H:i, d-m-Y',$post_row['date']).'</div>';
								}elseif($post_row['published']==1){
									echo ' <div class="alert alert-success">Published: '.date('H:i, d-m-Y',$post_row['date']).'</div>';
								}else{
									echo ' <div class="alert alert-info">Draft</div>';
								} ?>
								<div class="well well-small">
									<h4 class="sep">Publish</h4>
									<fieldset class="sep">
										<div class="control-group">
											<button class="btn btn-primary" id="save_submit" name="save_submit" type="submit"><i class="icon-download-alt icon-white"></i> <?php if($post_row['published']==0){echo 'Save Draft';}elseif($post_row['published']==1){echo 'Update';} ?></button>
											<button class="btn btn-danger saveDel" name="delete_submit" type="submit" onclick="return (confirm('Do you really want to delete this post\nand any images associated with it?'));"><i class="icon-trash icon-white"></i> Delete</button>
										</div>
									</fieldset>
									<fieldset>											
										<?php
										if($post_row['published']==0){ ?>
												<label class="checkbox">
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
												<button class="btn btn-success" id="pub_submit" name="pub_submit" type="submit"><i class="icon-arrow-up icon-white"></i> Publish</button>
										<?php
										}elseif($post_row['published']==1){ ?>
												<button class="btn btn-warning" name="unpub_submit" type="submit"><i class="icon-arrow-down icon-white"></i> Unpublish</button>
											<?php
										}else{echo 'Database corruption in the Publish field. e.g. it is NULL, non-numeric or not a valid number.';} ?>
									</fieldset>
								</div>
								<div class="well well-small">
									<h4 class="sep">Tags</h4>
									<fieldset>
										<input type="text" class="input-small" id="edit_tags" name="edit_tags" style="margin-bottom:0px;"> <button class="btn btn-inverse btn-small" name="add_tag" id="add_tag" type="submit"><i class="icon-plus icon-white"></i></button>
										<span class="help-block">Comma delimited.</span>
									</fieldset>
									<ul id="tagList">
									<?php
									if(!empty($post_row['tags'])){
										$exploded_tag = explode(',',$post_row['tags']);
										foreach($exploded_tag as $tag){
											echo '<li><span>'.htmlentities(stripslashes($tag)).'</span> <strong>&times;</strong></li>';
										}
									} ?>
									</ul>
								</div>
							</div>
						</form>						
						<div class="span3">
							<div class="well well-small">
								<h4 class="sep">Attached Media</h4>
								<?php
								$images = glob('img/'.$id.'/*.{jpg,gif,png,jpeg,bmp,JPG,GIF,PNG,JPEG,BMP}', GLOB_BRACE); ?>
								<div class="attachedImages">
									<form>
										<input type="hidden" name="fileUploadID" value="<?php echo $id; ?>"/>
										<input type="file" name="image_upload_file" size="5"/>
										<input type="button" id="image_upload_file_button" value="Upload"/>
										<div id="upload"></div>
									</form>
									<div id="imageList">
										<ul>
											<?php
											foreach($images as $image){
												$imageName = substr($image, strrpos($image, "/") + 1); ?>
												<li><span><?php echo $imageName; ?></span> <strong>&times;</strong></li>
												<?php 
											} ?>
										</ul>
									</div>
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
				
				<form class="form-horizontal" action="index.php?n=settings" method="post" name="setup_form">
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
				<form class="form-horizontal" action="index.php?n=settings" method="post" name="pass_form">
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
