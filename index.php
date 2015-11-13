<?php
require_once __DIR__.'/php/Surikat/ScrapFTS/autoload.inc.php';
use RedCat\Wire\Di;
$di = Di::getInstance();

$crawler = $di->create('Surikat\ScrapFTS\Crawler');
$url = $di->create('Surikat\ScrapFTS\Url');
$bases = $di->create('RedCat\DataMap\Bases');
$bases['scrapfts'] = ['dsn'=>'sqlite:'.REDCAT_CWD.'.data/scrapfts.sqlite'];
$db = $bases['scrapfts'];

//$db->debug();

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
	//echo $path."\n";
	//echo $title."\n";
	//echo str_replace("\n",'\n',$content)."\n\n";
	$db['page'][$path] = ['content_fulltext_'=>$content,'title'=>$title];
});
$crawler->enableSubdomains();
$crawler->scrap($url->getBaseHref(),['*/main/dl/dd','//title','*/main/article']);
echo "Done\n";
echo "</pre>";