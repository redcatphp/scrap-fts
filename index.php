<?php
require_once __DIR__.'/php/Wild/ScrapFTS/autoload.inc.php';
use Wild\Kinetic\Di;
$di = Di::getInstance();

$crawler = $di->create('Wild\ScrapFTS\Crawler');
$url = $di->create('Wild\ScrapFTS\Url');
$bases = $di->create('Wild\DataMap\Bases');
$bases['scrapfts'] = ['dsn'=>'sqlite:'.SURIKAT_CWD.'.data/scrapfts.sqlite'];
$db = $bases['scrapfts'];

$crawler->setContentCallback(function($path,$content,$title)use($db){
	$db['page'][$path] = ['content_fulltext_'=>$content,'title'=>$title];
});
$crawler->enableSubdomains();
$crawler->scrap($url->getBaseHref(),'*/main/article');

//echo '<pre>';print_r($crawler->getContents());echo '</pre>';