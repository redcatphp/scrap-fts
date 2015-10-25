<?php
namespace Wild\ScrapFTS;
class Crawler{
	private $urls = [];
	private $hashs = [];
	private $contents = [];
	private $contentCallback;
	private $enableSubdomains;
	private $domainSubstitution;
	function __construct($contentCallback=null,$domainSubstitution=null){
		if($contentCallback)
			$this->setContentCallback($contentCallback);
		if($domainSubstitution)
			$this->setDomainSubstitution($domainSubstitution);
	}
	function setDomainSubstitution($domainSubstitution){
		$this->domainSubstitution = $domainSubstitution;
	}
	function setContentCallback($contentCallback){
		$this->contentCallback = $contentCallback;
	}
	function enableSubdomains($enableSubdomains=true){
		$this->enableSubdomains = $enableSubdomains;
	}
	function scrap($url,$selector='body'){
		if(in_array($url,$this->urls))
			return;
			
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_exec($ch);
		$mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
		if(substr($mime,0,10)!='text/html;')
			return;
		
		$this->urls[] = $url;
		
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
			
			if(substr($href,0,7)=='mailto:'||substr($href,0,11)=='javascript:')
				continue;
			
			if(strpos($href,'://')!==false){
				if(!$this->enableSubdomains)
					continue;
				if($this->getDomain($baseHref)!=$this->getDomain($href))
					continue;
			}
			
			$p = strpos($path,'#');
			if($p){
				$hash = substr($path,$p+1);
				$path = substr($path,0,$p);
			}
			if(strpos($href,'://')===false){
				$href = $baseHref;
				if($path)
					$href = rtrim($href,'/').'/'.$path;
			}
			if($p){
				if(!isset($this->hashs[$href])||!in_array($hash,$this->hashs[$href]))
					$this->hashs[$href][] = $hash;
			}
			
			$this->scrap($href,$selector,$path);			
		}
		
		$title = $dom->getElementsByTagName('title')[0]->textContent;

		$xpath = new \DOMXpath($dom);
		$content = $dom->saveHTML($xpath->query($selector)[0]);
		if(!isset($this->hashs[$url])){
			$this->addContent($url,$content,$title);
		}
		else{
			$delimiters = [];
			foreach($this->hashs[$url] as $h)
				$delimiters[] = 'id="'.$h.'"';
			$x = $this->multiExplode($delimiters,$content);
			$l = count($x)-1;
			foreach($x as $i=>$content){
				$h = $i?'#'.$this->hashs[$url][$i-1]:'';
				if(!$i)
					$content = $content.'>';
				elseif($i==$l)
					$content = '<'.$content;
				else
					$content = '<'.$content.'>';
				$this->addContent($url.$h,$content,$title.($hash?' '.$hash:''));
			}
		}
		
		
	}
	private function multiExplode($delimiters,$string) {
		return explode($delimiters[0],strtr($string,array_combine(array_slice($delimiters,1),array_fill(0,count($delimiters)-1,array_shift($delimiters)))));
	}
	private function getDomain($path){
		$parts = parse_url($path);
		$host_names = explode('.', $parts['host']);
		$c = count($host_names);
		return $host_names[$c-2].'.'.$host_names[$c-1];
	}
	private function addContent($path,$content,$title){
		$content = preg_replace('/\s+/', ' ', strip_tags($content));
		$title = preg_replace('/\s+/', ' ', $title);
		if($this->domainSubstitution){
			$path = str_replace($this->getDomain($path),$this->domainSubstitution,$path);
		}
		if($this->contentCallback)
			call_user_func($this->contentCallback,$path,$content,$title);
		else
			$this->contents[$path] = [$content,$title];
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