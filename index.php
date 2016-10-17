<?php

$url = isset($_POST['url'])?$_POST['url']:'';
$dbfile = isset($_POST['dbfile'])?$_POST['dbfile']:'';
$domain = isset($_POST['domain'])?$_POST['domain']:'';
$subdomains = isset($_POST['subdomains'])?$_POST['subdomains']:false;
$mainselector = isset($_POST['mainselector'])?$_POST['mainselector']:'body, title';
$url2filename = trim(substr($url,6),'/');
if(!$dbfile){
	$dbfile = '.data/'.$url2filename;
}
if(substr($dbfile,-1)=='/'){
	$dbfile .= $url2filename;
}
if(substr($dbfile,0,1)!='/'){
	$dbfile = getcwd().'/'.$dbfile;
}
if(!in_array(strtolower(pathinfo($dbfile,PATHINFO_EXTENSION)),['db','sql','sqlite'])){
	$dbfile .= '.sqlite';
}

error_reporting(-1);
ini_set('display_startup_errors',true);
ini_set('display_errors','stdout');

?>
<!DOCTYPE html>
<html>
<head>
	<style>
		label,input{
			display:block;
			float:left;
		}
		label{
			width:200px;
		}
		input[type="text"]{
			width:800px;
		}
	</style>
</head>
<body>
	<form action="" method="POST">
		<fieldset class="form-group">
			<label for="url">URL *</label>
			<input id="url" name="url" type="text" value="<?php echo $url;?>" required>
		</fieldset>
		<fieldset class="form-group">
			<label for="subdomains">Enable Subdomains</label>
			<input id="subdomains" name="subdomains" type="checkbox" value="1" <?php echo $subdomains?'checked="checked"':''?>>
		</fieldset>
		<fieldset class="form-group">
			<label for="mainselector">Selectors</label>
			<input id="mainselector" name="mainselector" type="text" value="<?php echo $mainselector;?>">
		</fieldset>
		<fieldset class="form-group">
			<label for="domain">Domain Substitution</label>
			<input id="domain" name="domain" type="text" value="<?php echo $domain;?>">
		</fieldset>
		<fieldset class="form-group">
			<label for="dbfile">DB File</label>
			<input id="dbfile" name="dbfile" type="text" value="<?php echo $dbfile;?>">
		</fieldset>
		<fieldset class="form-group">
			<input type="submit">
		</fieldset>
	</form>
</body>
</html>
<?php
if(!isset($_POST['url'])) return;

require_once 'vendor/autoload.php';

use RedCat\ScrapFTS\Crawler;
use RedCat\DataMap\Bases;
use Symfony\Component\CssSelector\CssSelectorConverter;

$crawler = new Crawler();
if($subdomains){
	$crawler->enableSubdomains(true);
}
if($domain){
	$crawler->setDomainSubstitution($domain);
}

$bases = new Bases();
$bases['scrapfts'] = ['dsn'=>'sqlite:'.$dbfile];
$db = $bases['scrapfts'];
//$db->debug();

$converter = new CssSelectorConverter();

ignore_user_abort(false);
set_time_limit(0);
if(php_sapi_name()!='cli'){
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
	header("Last-Modified: " . gmdate("D, d M Y H:i:s" ) . " GMT" );
	header("Pragma: no-cache");
	header("Cache-Control: no-cache");
	header("Expires: -1");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Cache-Control: no-store, no-cache, must-revalidate");
	ob_implicit_flush(true);
	@ob_end_flush();
	echo str_repeat(" ",1024);
}

echo "<pre>";
if($db->tableExists('page'))
	$db->drop('page');
$crawler->setContentCallback(function($path,$content,$title)use($db){
	echo $path."\n";
	//echo $title."\n";
	//echo str_replace("\n",'\n',$content)."\n\n";
	//dd($content);
	$db['page'][$path] = ['content_fulltext_'=>$content,'title'=>$title];
});
$crawler->enableSubdomains();
$crawler->scrap($url,[$converter->toXPath($mainselector)]);
echo "Done\n";
echo "</pre>";