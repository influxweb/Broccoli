<?php
header('Content-type: text/xml');
include('flameblog_db_settings.php'); //Load global settings
//Connect to DB: DO NOT EDIT! //////////////////////////////
    $con = mysql_connect($flameblog_database_host,$flameblog_database_username,$flameblog_database_password);
	if(!$con){
		die('Could not connect: ' . mysql_error());
	}else {
		$db = mysql_select_db($flameblog_database_name);
		if(!$db){
			die('Could not connect to database: ' . mysql_error());
		}
	}
////////////////////////////////////////////////////////////

$permalink_prefix = $_GET['url'];
$sq='SELECT title FROM flameblog_settings LIMIT 1';
$sdoGet=mysql_query($sq);
$sresult = mysql_fetch_assoc($sdoGet);
?>
<rss version="2.0">
<channel>
<title><?php if($_GET['tag']!=''){ echo 'Tag: '.$_GET['tag'].' | '; } echo $sresult['title']; ?></title>
<description></description>
<link><?php echo $permalink_prefix; ?></link>

<?php
$q='SELECT id,title FROM flameblog_posts WHERE published = "1" AND tags LIKE "%'.mysql_real_escape_string($_GET['tag']).'%" ORDER BY date DESC LIMIT 0,15';
$doGet=mysql_query($q);
while($result = mysql_fetch_array($doGet)){ ?>
     <item>
        <title>
			<?php echo htmlentities(strip_tags($result['title'])); ?>
		</title>
        <link>
			<?php echo $permalink_prefix.'?id='.$result['id']; ?>
		</link>
     </item>  
	<?php 
} ?>  

</channel>
</rss>