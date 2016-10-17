<?php
require_once 'vendor/autoload.php';

use RedCat\ScrapFTS\Crawler;
use RedCat\DataMap\Bases;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Exception;

error_reporting(-1);
ini_set('display_startup_errors',true);
ini_set('display_errors','stdout');

$keys = [
	'configdir',
	'url',
	'dbfile',
	'substitution',
	'subdomains',
	'selectors',
];

foreach($keys as $key){
	$$key = isset($_POST[$key])?$_POST[$key]:'';
}

if($configdir){
	$json = file_get_contents($configdir.'/.scrapfts');
	$json = json_decode($json,true);
	if(!$json){
		throw new Exception('invalid json format in '.$configdir.'/.scrapfts');
		return;
	}
	foreach($keys as $key){
		if($$key==''&&isset($json[$key])){
			$$key = $json[$key];
		}
	}
}

$url2filename = trim(substr($url,6),'/');
if($url){
	if(!$dbfile){
		$dbfile = '.data/'.$url2filename;
	}
	if(substr($dbfile,-1)=='/'){
		$dbfile .= $url2filename;
	}
	if(substr($dbfile,0,1)!='/'){
		$dbfile = ($configdir?$configdir:getcwd()).'/'.$dbfile;
	}
	if(!in_array(strtolower(pathinfo($dbfile,PATHINFO_EXTENSION)),['db','sql','sqlite'])){
		$dbfile .= '.sqlite';
	}
	if(!$selectors){
		$selectors = 'body, title';
	}
}

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
			<label for="configdir">Autoconf Dir .scrapfts</label>
			<input id="configdir" name="configdir" type="text" value="<?php echo $configdir;?>">
		</fieldset>
		<fieldset class="form-group">
			<label for="url">URL</label>
			<input id="url" name="url" type="text" value="<?php echo $url;?>">
		</fieldset>
		<fieldset class="form-group">
			<label for="subdomains">Enable Subdomains</label>
			<input id="subdomains" name="subdomains" type="checkbox" value="1" <?php echo $subdomains?'checked="checked"':''?>>
		</fieldset>
		<fieldset class="form-group">
			<label for="selectors">Selectors</label>
			<input id="selectors" name="selectors" type="text" value="<?php echo $selectors;?>">
		</fieldset>
		<fieldset class="form-group">
			<label for="substitution">Href Target Substitution</label>
			<input id="substitution" name="substitution" type="text" value="<?php echo $substitution;?>">
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

$crawler = new Crawler();
if($subdomains){
	$crawler->enableSubdomains(true);
}
if($substitution){
	$crawler->setDomainSubstitution($substitution);
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
$selectorsArray = [];
foreach(explode(',',$selectors) as $selector){
	$selectorsArray[] = $converter->toXPath(trim($selector));
}
$crawler->scrap($url,$selectorsArray);
chmod($dbfile,0777);
echo "Done\n";
echo "</pre>";