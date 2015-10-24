<?php
namespace Wild\ScrapFTS;
class Crawler{
	private $urls = [];
	private $uris = [];
	private $hashs = [];
	private $contents = [];
	private $contentCallback;
	function __construct($contentCallback=null){
		if($contentCallback)
			$this->setContentCallback($contentCallback);
	}
	function setContentCallback($contentCallback){
		$this->contentCallback = $contentCallback;
	}
	function scrap($url,$selector='body',$uri=''){
		if(in_array($url,$this->urls))
			return;
			
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		$mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		if(substr($mime,0,10)!='text/html;')
			return;
		
		$this->urls[] = $url;
		$this->uris[] = $uri;
		
		$dom = new \DOMDocument('1.0');
		@$dom->loadHTMLFile($url);
		
		$baseTag = $dom->getElementsByTagName('base');
		if(isset($baseTag[0])){
			$baseHref = $baseTag[0]->getAttribute('href');
		}
		else{
			$parts = parse_url($url);
			$baseHref = $parts['scheme'] . '://';
			if(isset($parts['user']) && isset($parts['pass']))
				$baseHref .= $parts['user'].':'.$parts['pass'].'@';
			$baseHref .= $parts['host'];
			if(isset($parts['port']))
				$baseHref .= ':'.$parts['port'];
			$baseHref .= $parts['path'];
		}
		
		$anchors = $dom->getElementsByTagName('a');
		foreach($anchors as $element){
			$href = $element->getAttribute('href');
			$path = ltrim($href, '/');
			$path = rtrim($href, '#');
			if(strpos($href,'://')!==false||substr($href,0,7)=='mailto:')
				continue;
			
			$p = strpos($path,'#');
			if($p){
				$hash = substr($path,$p+1);
				$path = substr($path,0,$p);
				if(!isset($this->hashs[$path])||!in_array($hash,$this->hashs[$path]))
					$this->hashs[$path][] = $hash;
			}
			
			$href = $baseHref;
			if($path)
				$href = rtrim($href,'/').'/'.$path;
			$this->scrap($href,$selector,$path);			
		}
		
		$xpath = new \DOMXpath($dom);
		$content = $dom->saveHTML($xpath->query($selector)[0]);
		if(!isset($this->hashs[$uri])){
			$this->addContent($uri,$content);
		}
		else{
			$delimiters = [];
			foreach($this->hashs[$uri] as $h)
				$delimiters[] = 'id="'.$h.'"';
			$x = $this->multiExplode($delimiters,$content);
			$l = count($x)-1;
			foreach($x as $i=>$content){
				$h = $i?'#'.$this->hashs[$uri][$i-1]:'';
				if(!$i)
					$content = $content.'>';
				elseif($i==$l)
					$content = '<'.$content;
				else
					$content = '<'.$content.'>';
				$this->addContent($uri.$h,$content);
			}
		}
		
		
	}
	private function multiExplode($delimiters,$string) {
		return explode($delimiters[0],strtr($string,array_combine(array_slice($delimiters,1),array_fill(0,count($delimiters)-1,array_shift($delimiters)))));
	}
	private function addContent($path,$content){
		$content = preg_replace('/\s+/', ' ', strip_tags($content));
		if($this->contentCallback)
			call_user_func($this->contentCallback,$path,$content);
		else
			$this->contents[$path] = $content;
	}
	function getUrls(){
		return $this->urls;
	}
	function getUris(){
		return $this->uris;
	}
	function getHashs(){
		return $this->hashs;
	}
	function getContents(){
		return $this->contents;
	}
}