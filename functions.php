<?php
//Connect to DB: DO NOT EDIT! //
function broccoli(){
	include_once('db_settings.php');
	$con = mysql_connect($database_host,$database_username,$database_password);
	if(!$con){
		die('Could not connect: ' . mysql_error());
	}else {
		$db = mysql_select_db($database_name);
		if(!$db){
			die('Could not connect to database: ' . mysql_error());
		}
	}

	//Sort out GET variables
	global $broc_id;
	global $broc_page;
	global $broc_tag;
	if(isset($_GET['id'])){$broc_id = htmlentities($_GET['id'], ENT_QUOTES);}
	if(isset($_GET['page'])){$broc_page = htmlentities($_GET['page'], ENT_QUOTES);}else{$broc_page = 1;}
	if(isset($_GET['tag'])){$broc_tag = htmlentities($_GET['tag'], ENT_QUOTES);}

	//create IP checking table if doesn't exist
	mysql_query("CREATE TABLE IF NOT EXISTS broccoli_ip_tmp (ipAgent int(40) NOT NULL,post_id int(6) NOT NULL,time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=MyISAM DEFAULT CHARSET=latin1");

	//Remove old post ip records
	mysql_query("DELETE FROM broccoli_ip_tmp WHERE time < now() - interval 15 minute");
	
	//Settings
	$settings_query = 'SELECT per_page FROM broccoli_settings LIMIT 1';
	$settings_result = mysql_query($settings_query);
	if(mysql_num_rows($settings_result)==0){ //if setting row hasn't been created yet
		mysql_query("INSERT INTO broccoli_settings (id, pass, email, per_page) VALUES (1, '', '', 3)");
		$settings_query = 'SELECT per_page FROM broccoli_settings LIMIT 1';
		$settings_result = mysql_query($settings_query);
	}
	$settings_row = mysql_fetch_assoc($settings_result);
	$per_page = $settings_row['per_page'];
	
	//heat stuff
	$ipAgent = sha1($_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT']);
	if(isset($_GET['id'])){
		$ip_query = 'SELECT ipAgent FROM broccoli_ip_tmp WHERE ipAgent = "'.$ipAgent.'" AND post_id = "'.$_GET['id'].'"';
		$ip_result = mysql_query($ip_query) or die(mysql_error());
		$ip_num_rows = mysql_num_rows($ip_result);
		if($ip_num_rows<1){
			//Add heat to a post
			mysql_query('UPDATE broccoli_posts SET heat = heat+1 WHERE id = "'.$_GET['id'].'"') or die(mysql_error());
			//Add ip and post id to temp ip table
			mysql_query('INSERT INTO broccoli_ip_tmp (ipAgent,post_id) VALUES ("'.$ipAgent.'","'.$_GET['id'].'")') or die(mysql_error());
		}
	}
	
	//Posts query (dependent on situation: post page or list)
	if(isset($broc_id)){ //individual posts
		$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM broccoli_posts WHERE published = "1" AND date < now() AND id = "'.$broc_id.'"';
	}else{ //lists
		$limit = (($broc_page*$per_page)-$per_page).','.$per_page; //for limiting the results brought back for pagination
		$post_query = 'SELECT id,UNIX_TIMESTAMP(date) as date,title,content,tags,published,heat FROM broccoli_posts WHERE published = "1" AND date < now() AND tags LIKE "%'.$broc_tag.'%" ORDER BY date DESC LIMIT '.$limit;
		$total_query = 'SELECT count(id) as total FROM broccoli_posts WHERE published = "1" AND date < now() AND tags LIKE "%'.$broc_tag.'%"';
		$total_result = mysql_query($total_query) or die(mysql_error());
		$total_row = mysql_fetch_assoc($total_result);
		global $broc_max_pages;
		$broc_max_pages = ceil($total_row['total']/$per_page);
	}
	$post_result = mysql_query($post_query) or die(mysql_error());
	global $broc_post;
	$broc_post = array();
	$i = 0;
	while ($post_row = mysql_fetch_assoc($post_result)){
		if(!empty($post_row['tags'])){
			$exploded_tags = explode(',',$post_row['tags']);
		}else{
			$exploded_tags = '';
		}
		$broc_post[$i] = array("id"=>$post_row['id'],"title"=>$post_row['title'],"content"=>$post_row['content'],"tags"=>$exploded_tags,"date"=>$post_row['date'],"heat"=>$post_row['heat']);
		$i++;
	}
	if(isset($broc_id)){ //only on post page
		global $broc_previousID;
		global $broc_nextID;
		$broc_previousID = mysql_fetch_row(mysql_query('SELECT id FROM broccoli_posts WHERE UNIX_TIMESTAMP(date)<'.$broc_post[0]['date'].' ORDER BY date DESC LIMIT 1'));
		$broc_previousID = $broc_previousID[0];
		$broc_nextID = mysql_fetch_row(mysql_query('SELECT id FROM broccoli_posts WHERE UNIX_TIMESTAMP(date)>'.$broc_post[0]['date'].' ORDER BY date ASC LIMIT 1'));
		$broc_nextID = $broc_nextID[0];
	}
}
broccoli();
?>
