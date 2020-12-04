<?php
error_reporting(0);

if(preg_match('~yandex_(.*?)\.html~i', getenv('REQUEST_URI'), $mtch)){ die('<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>Verification: '.$mtch[1].'</body></html>'); }

$client = new Client;
if (isset($_GET["u"])) $client->proxy();
elseif (isset($_GET["delcache"])||isset($_GET["delpage"]))
	$client->delCache($_GET['delcache'], $_GET['delpage']);
if($content = $client->getContent()) echo str_replace('#$DOMAIN$#', getenv('HTTP_HOST'), $content);
else {usleep(500000); echo str_replace('#$DOMAIN$#', getenv('HTTP_HOST'), $client->getContent());}

class Client {
	
	private $server = "zaim-profi.ru"; // адрес донора (для загрузчика можно использовать метку #DONOR#)
	private $server_path = ""; //не трогать
	private $client_folder = ""; //не трогать

	// кеширование:
	private $cache = 1; // включить кеширование страниц на Клиенте (1 - ДА, 0 - НЕТ)
	
	private $backup404 = 1; // если Сервер не доступен и страница на Клиенте еще не закеширована, то выведет рандомную закешированную страницу (1 - ДА, 0 - НЕТ)
	#! если НЕТ, то Клиент выведет статус 404 и контент "404 - Not Found!"

	private $cachedir = 'cache'; // папка для кеша (без "/")
	#! чтобы очистить кеш - удалите содержимое папки для кеша
	private $cacheFiles = 1900; // максимальное кол-во файлов в папках для кеша
	private $cacheDirEnable = 1; // включить создание новых папок для кеша при превышении $cacheFiles (1 - ДА, 0 - НЕТ)

	// --------------------- //
	
	function getContent() {
		$path = $this->server_path.$this->path;
		
		if ($this->cache) $cachelink = $this->cache();
		
			$ch = curl_init();
			curl_setopt ($ch, CURLOPT_URL, 'http://'.$this->server.$path);
			//curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)');
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,1);
      curl_setopt ($ch, CURLOPT_REFERER, 'http://'.$this->server);
			curl_setopt ($ch, CURLOPT_HEADER, 1);
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 60 );
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false); 
			curl_setopt ($ch, CURLOPT_TIMEOUT, 60 );
			curl_setopt ($ch, CURLOPT_COOKIEJAR, $this->cachedir . '/' .'cookies.txt');  
			curl_setopt ($ch, CURLOPT_COOKIEFILE, $this->cachedir . '/' .'cookies.txt');
			if ($res = curl_exec($ch)){
				$info = curl_getinfo($ch);
				$header_size = $info['header_size'];
				$header = substr($res, 0, $header_size);
				$result = substr($res, $header_size);
			}	curl_close($ch);

		
		// set Server cookies
		if (!empty($header)){
			preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
			$cookies = array();
			foreach($matches[1] as $item) {
				parse_str($item, $cookie);
				$cookies = array_merge($cookies, $cookie);
			} foreach($cookies as $name=>$val)
				header("Set-Cookie: $name=$val");
		}
		
		// set Server status
		if (empty($info['http_code'])) $info['http_code'] = 400;
		header(((isset($_SERVER['SERVER_PROTOCOL'])?$_SERVER['SERVER_PROTOCOL']:'HTTP/1.0')).' '.$info['http_code'].' '.$this->get_status($info['http_code']));
		header("Referer: ".getenv('HTTP_REFERER')); 
		
		if (!empty($info['redirect_url'])) {
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
			header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1 
			header("Pragma: no-cache"); // HTTP/1.1 
			header("Last-Modified: ".gmdate("D, d M Y H:i:s")."GMT");
			header("Location: ".str_replace($this->server_path, '', $info['redirect_url'])); exit;
		}
		
		if ($info['http_code'] == 200)
			header("Content-Type: {$this->contentType}");
		
		$type = $this->type; $answer='n';
		if (!empty($result) and $info['http_code']!=403&&$info['http_code']!=400){
			if($type == 'html') {
				
        if(preg_match("~<meta(?!\s*(?:name|value)\s*=)[^>]*?charset\s*=[\s\"']*([^\s\"'/>]*)~is", $result, $m) && $m[1] == 'windows-1251'){
          $result = preg_replace("~charset=(\s|'|\")?windows-1251~is", 'charset=utf-8', iconv("WINDOWS-1251", "UTF-8", $result)); }
       
        $result = preg_replace_callback("~<head>(.*?)</head>~is", "strip_head", $result); //временное решение
				$result = str_ireplace("https:", "http:", $result); 
				
                /*$result = preg_replace("!<script[^>]*>(.)*</script>!Uis","",$result); 
				$result = preg_replace("!<noscript[^>]*>(.)*</noscript>!Uis","",$result); 
				$result = preg_replace("!<meta name=['\"]yandex-verification['\"][^>]*>!ius","",$result); 
				$result = preg_replace("!<meta name=['\"]google-site-verification['\"][^>]*>!ius","",$result); 
				$result = preg_replace("!</head>!i",'<!-- Yandex.Metrika counter --> <script type="text/javascript"> (function (d, w, c) { (w[c] = w[c] || []).push(function() { try { w.yaCounter44864800 = new Ya.Metrika({ id:44864800, clickmap:true, trackLinks:true, accurateTrackBounce:true }); } catch(e) { } }); var n = d.getElementsByTagName("script")[0], s = d.createElement("script"), f = function () { n.parentNode.insertBefore(s, n); }; s.type = "text/javascript"; s.async = true; s.src = "https://mc.yandex.ru/metrika/watch.js"; if (w.opera == "[object Opera]") { d.addEventListener("DOMContentLoaded", f, false); } else { f(); } })(document, window, "yandex_metrika_callbacks"); </script> <noscript><div><img src="https://mc.yandex.ru/watch/44864800" style="position:absolute; left:-9999px;" alt="" /></div></noscript> <!-- /Yandex.Metrika counter --> </head>',$result); 
				 */
				  
				//$result = preg_replace('~//(www\.|)'.$this->server.'~iu', '//'.getenv('HTTP_HOST'), $result);
				$result = preg_replace('~//(www\.|)'.$this->server.'~iu', '//#$DOMAIN$#', $result);
				file_put_contents($cachelink, $result);
			}else{
        if($type == 'txt' || $type == 'xml'){ //заботимся о robots и sitemap.xml
          $result = preg_replace('~(www\.|)'.$this->server.'~iu', '#$DOMAIN$#', $result); }
        file_put_contents($cachelink, $result);
      }

			return $result;
		}
		elseif (empty($result) && isset($result) && $type == 'html' or $info['http_code']==403||$info['http_code']==400) {
			// backup index
			$thispage = 'http://' . getenv('HTTP_HOST') . getenv('REQUEST_URI');
			$cachelink = $this->cachedir . '/' . $type. '/' . $this->crc32_strlen($thispage)."_backup.$type";
			if (is_file($cachelink)) return file_get_contents($cachelink);
			// pages
			if (!$this->backup404) {
				header("Status: 404 Not Found");
				$this->send_error(404);
			}
			// random_cache
			$glob = glob($this->cachedir . '/' . $type. '/1/*.html');
			if (!empty($glob))
				return file_get_contents(trim($glob[mt_rand(0,count($glob)-1)]));
			
			$this->send_error($info['http_code']);
		}
		elseif (empty($result) && isset($result) && $type != 'html') die;
		elseif (!isset($result))
			$this->send_error($info['http_code']);
	}

	function cache(){
		$type = $this->type;
		$cachedir = $this->cachedir;
		
		$thispage = 'http://' . getenv('HTTP_HOST') . getenv('REQUEST_URI');
		
		foreach($this->no_cache as $file)
			if (strpos($thispage, $file)!==false)
				return false;
		
		if(!is_dir($cachedir.'/'.$type)) mkdir($cachedir.'/'.$type, 0777, true);

		$cachedir = $cachedir . '/' . $type;
		
		$list = glob($cachedir . "/[0-9]*", GLOB_NOSORT | GLOB_ONLYDIR);
		$cache_folder = $cachedir. '/cache_folder.cfg';

		if(($new_dir = $list[count($list) - 1])==''){
			$new_dir = substr($new_dir, strrpos($new_dir, '/') + 1) + 1;
			mkdir($cachedir . '/' . $new_dir);
			$dir = array('dir' => $new_dir, 'files' => 1);
			file_put_contents($cache_folder, serialize($dir));
			$cachelink = $cachedir . '/' . $new_dir. '/' . $this->crc32_strlen($thispage).".$type";
			
		} else {

			$rrr = $this->rglob($this->crc32_strlen($thispage).".$type", $cachedir);
			
			if ($rrr) {
				if ($this->cron) {
					unlink($rrr);
					return $rrr;
				}
				header(((isset($_SERVER['SERVER_PROTOCOL'])?$_SERVER['SERVER_PROTOCOL']:'HTTP/1.0')).' 200 '.$this->get_status(200));
				header("Content-Type: {$this->contentType}");
				//readfile($rrr); //было так
        echo str_replace('#$DOMAIN$#', getenv('HTTP_HOST'), file_get_contents($rrr));
        
				die;
			}
			
			if ($this->cacheDirEnable) { 
				$fp = fopen($cache_folder, 'a+');
				flock($fp, LOCK_EX);
				fseek($fp, 0);
				$dir = trim(fgets($fp));
				ftruncate($fp, 0);
				
				if ($dir !== '') $dir = unserialize($dir);
				else {
					$dir = array('dir' => 1, 'files' => 0);
					$list = array("$cachedir/$dir[dir]");
					@mkdir("$cachedir/$dir[dir]");
				}
				
				if ($dir['files'] >= (int)$this->cacheFiles) {
					$dir['dir'] = $this->nextFolder ($cachedir);
					$dir['files'] = 0;
					mkdir($cachedir . '/' . $dir['dir']);
				}
				
				$dir['files']++;
				fwrite($fp, serialize($dir));
				fflush($fp);
				flock($fp, LOCK_UN);
				fclose($fp);
				
				$list = array("$dir[dir]");
				$new_dir = $dir['dir'];
			}
			else $new_dir = count($list);
			
			if( is_dir($cachedir . '/' . $new_dir) ){
				$cachelink = $cachedir . '/' . $new_dir. '/' . $this->crc32_strlen($thispage).".$type";
			} else {
				$badfolder = $this->badFolder($cachedir);
				mkdir($cachedir . '/' . $badfolder);
				$cachelink = $cachedir . '/' . $badfolder. '/' . $this->crc32_strlen($thispage).".$type";
			}
			
			if (count(glob($cachedir . '/' . $new_dir."/*")) >= $this->cacheFiles){
				if (!$this->cacheDirEnable) {
				
					$nf = '15';

					$scandir = scandir($cachedir . '/' . $new_dir.'/'); 

					foreach($scandir as $v) {
						if(preg_match("#\..*$#isU",$v)) {
							$a[] = $cachedir . '/' . $new_dir.'/'.$v;
							$aname[] = $v;
						}
					}

					for($i=0;$i<count($a);$i++){			
						$b[filemtime($a[$i])] = $a[$i];
					}

					if (@ksort($b)){

						foreach($b as $k=>$v) {
							$names[] = $v;
							$filemtime[] = date('d:m:Y H:i:s', $k);
						}
						if($nf > count($b)) $number = count($b); else $number = $nf;
					
						for($i=0;$i<$number;$i++){
							unlink($names[$i]);					
						}
					}			
				}
			}
		}
		return $cachelink;
	}

	function proxy(){
		
		ini_set('zlib.output_compression', 'On');
		ini_set('zlib.output_compression_level', '1');
		if (!function_exists('hex2bin')){
			function hex2bin($str){
				$len = strlen($str);
				for ($i = 0;$i<$len;$i+=2)
					$sbin .= pack("H*", substr($str, $i, 2));
				return $sbin;
			}
		}
		$ua = getenv('HTTP_USER_AGENT');
		
		$u = hex2bin($_GET["u"]);
		$timeout = $_GET["t"]?$_GET["t"]:5;
		$url = parse_url($u);
		if (function_exists("curl_exec") && function_exists("curl_init")) {
			$ch = curl_init($u);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"User-Agent: $ua",
				"Referer: http://{$url['host']}/",
				"Host: {$url['host']}")
			);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout );
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout );
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false );
			curl_setopt($ch, CURLOPT_COOKIE, mt_rand(11111,99999) . '=' . mt_rand(11111,99999)); 
			$result = curl_exec( $ch );
			curl_close($ch);
		}
		else {
			$result = '';
			$socket = ($url['scheme']=='https') ? 
				fsockopen("ssl://{$url['host']}", 443, $errno, $errstr, 30):
				fsockopen($url['host'], 80, $errno, $errstr, 30);
			if ($socket) {
				fputs($socket, "GET ".(isset($url['path'])? $url['path']: '/') . (isset($url['query'])? '?' . $url['query']: '')." HTTP/1.0\r\n");
				fputs($socket, "Host: {$url['host']}\r\n");
				fputs($socket, "Referer: http://{$url['host']}/\r\n");
				fputs($socket, "User-Agent: $ua\r\n");
				fputs($socket, "Connection: close\r\n\r\n");
				while (!feof($socket))
					$result .= fgets($socket, 128);
				fclose($socket);
			}
		}
		list($header, $result) = explode("\r\n\r\n", $result, 2);
		if (preg_match("~Location: (.*)~", $header, $m)) {
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); 
			header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1 
			header("Pragma: no-cache"); // HTTP/1.1 
			header("Last-Modified: ".gmdate("D, d M Y H:i:s")."GMT");
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: ?u=".bin2hex(trim($m[1])).'&t='.$timeout); die;
		}
		die($result);
	}
	
	function getUserIP() {
		$array = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR', 'HTTP_X_REMOTECLIENT_IP');
		foreach($array as $key)
			if(filter_var(getenv($key), FILTER_VALIDATE_IP)) return getenv($key);
		return false;
	}
	function rglob($file, $dir) {
		$files = glob($dir."/[0-9]*/".$file, GLOB_NOSORT);
		if(count($files) > 0) return $files[0];
		else return false;		 
	}
	function badFolder($cachedir){
		if (!glob( $cachedir. '/[0-9]*', GLOB_NOSORT | GLOB_ONLYDIR ) == '') {
			foreach (glob( $cachedir. '/[0-9]*', GLOB_NOSORT | GLOB_ONLYDIR ) as $filename) {
				$array[] = $filename;
			}
			$array = str_replace( $cachedir. '/', '', $array );
			$array_r=range(min($array),max($array));
			for($i=0;$i<count($array_r);++$i){
				if(!in_array($array_r[$i],$array)){
					$badfolder[] = $array_r[$i];
				}
			}
			return $badfolder[0];
		} else return '1';
	}
	function nextFolder($cachedir){
		foreach (glob( $cachedir. '/[0-9]*', GLOB_NOSORT | GLOB_ONLYDIR ) as $filename) {
			$array[] = $filename;
		}
		$array = str_replace( $cachedir. '/', '', $array );
		$array_r=range(1,max($array));
		$badfolder = array();
		for($i=0;$i<count($array_r);$i++){
			if(!in_array($array_r[$i],$array)){
				$badfolder[] = $array_r[$i];
			}
		}
		
		if (count($badfolder)>0) return $badfolder[0];
		else return (int)max($array)+1;
	}
	function delCache($delcache = '', $delpage = '', $type = 'html') {
		if (!getenv('HTTP_REFERER') || parse_url(getenv('HTTP_REFERER'), PHP_URL_HOST)!=$this->server)
			$this->send_error(403);
		$cachedir = $this->cachedir;
		if (!empty($delcache)) {
			$this->delTree($cachedir, $cachedir);
			echo 'Done!';		
		} else {
			$rrr = $this->rglob($this->crc32_strlen($delpage).".$type", $cachedir . '/' . $type);
			if ($rrr) {unlink($rrr);echo 'Done!';}
			else echo 'File not found!';
		}
		die;
	}
	function delTree($dir, $cachedir) {
		if ($objs = glob($dir."/*")) {
			foreach($objs as $obj) {
				if (is_dir($obj))$this->delTree($obj, $cachedir);
				else {chmod($cachedir,0777);unlink($obj);}
			}
		}
		if ($dir !== $cachedir) @rmdir($dir);
	}
	function crc32_strlen($str) {
		return sprintf("%u", crc32($str)).mb_strlen($str);
	}
	//@{ HTTP status codes (RFC 2616)
	function get_status($code){
		$HTTP[100]='Continue';
		$HTTP[101]='Switching Protocols';
		$HTTP[200]='OK';
		$HTTP[201]='Created';
		$HTTP[202]='Accepted';
		$HTTP[203]='Non-Authorative Information';
		$HTTP[204]='No Content';
		$HTTP[205]='Reset Content';
		$HTTP[206]='Partial Content';
		$HTTP[300]='Multiple Choices';
		$HTTP[301]='Moved Permanently';
		$HTTP[302]='Found';
		$HTTP[303]='See Other';
		$HTTP[304]='Not Modified';
		$HTTP[305]='Use Proxy';
		$HTTP[307]='Temporary Redirect';
		$HTTP[400]='Bad Request';
		$HTTP[401]='Unauthorized';
		$HTTP[402]='Payment Required';
		$HTTP[403]='Forbidden';
		$HTTP[404]='Not Found';
		$HTTP[405]='Method Not Allowed';
		$HTTP[406]='Not Acceptable';
		$HTTP[407]='Proxy Authentication Required';
		$HTTP[408]='Request Timeout';
		$HTTP[409]='Conflict';
		$HTTP[410]='Gone';
		$HTTP[411]='Length Required';
		$HTTP[412]='Precondition Failed';
		$HTTP[413]='Request Entity Too Large';
		$HTTP[414]='Request-URI Too Long';
		$HTTP[415]='Unsupported Media Type';
		$HTTP[416]='Requested Range Not Satisfiable';
		$HTTP[417]='Expectation Failed';
		$HTTP[500]='Internal Server Error';
		$HTTP[501]='Not Implemented';
		$HTTP[502]='Bad Gateway';
		$HTTP[503]='Service Unavailable';
		$HTTP[504]='Gateway Timeout';
		$HTTP[505]='HTTP Version Not Supported';
		return $HTTP[$code];
	}
	
	function send_error($code){
		header(((isset($_SERVER['SERVER_PROTOCOL'])?$_SERVER['SERVER_PROTOCOL']:'HTTP/1.0')).' '.$code.' '.$this->get_status($code));
		die($code. ' - '.$this->get_status($code) . "\n");
	}
	
	function __construct(){
		
		$server = $this->crc32_strlen($this->server);
		$this->no_cache = array("/{$server}.js", "/{$server}.gif");
		
		$this->cron = getenv('HTTP_X_CRON_CACHE')?1:0;
		
		$this->path = substr(getenv('REQUEST_URI'), strlen($this->client_folder));
		switch(true) {
			case strpos($this->path, '.css')!==false:
				$ct = 'text/css; charset=utf-8';
				$type = 'css';
			break;
			case strpos($this->path, '.png')!==false:
				$ct = 'image/png';
				$type = 'png';
			break;
			case strpos($this->path, '.jpg')!==false:
			case strpos($this->path, '.jpeg')!==false:
				$ct = 'image/jpeg';
				$type = 'jpg';
			break;
			case strpos($this->path, '.gif')!==false:
				$ct = 'image/gif';
				$type = 'gif';
			break;
			case strpos($this->path, '.ico')!==false:
				$ct = 'image/x-icon';
				$type = 'ico';
			break;
			case strpos($this->path, '.xml')!==false:
			case strpos($this->path, '.rss')!==false:
			case strpos($this->path, '/rss/')!==false:
			case strpos($this->path, '/rss')!==false:
				$ct = 'text/xml; charset=utf-8';
				$type = 'xml';
			break;
			case strpos($this->path, '.txt')!==false:
				$ct = ' text/plain; charset=utf-8';
				$type = 'txt';
			break;
			case strpos($this->path, '.js')!==false:
				$ct = 'text/javascript; charset=utf-8';
				$type = 'js';
			break;
			case strpos($this->path, '.exe')!==false:
				$ct = 'application/octet-stream';
				$type = 'exe';
			break;
			case strpos($this->path, '.zip')!==false:
				$ct = 'application/zip';
				$type = 'zip';
			break;
			case strpos($this->path, '.mp3')!==false:
				$ct = 'application/mpeg';
				$type = 'mp3';
			break;
			case strpos($this->path, '.mpg')!==false:
				$ct = 'application/mpeg';
				$type = 'mpg';
			break;
			case strpos($this->path, '.avi')!==false:
				$ct = 'application/x-msvideo';
				$type = 'avi';
			break;
			default:
				$ct = 'text/html; charset=utf-8';
				$type = 'html';
			break;
		}
		$this->type = $type;
		$this->contentType = $ct;
	}
}

#Модный var_dump
function xx($v){ echo '<pre>'; die(var_dump($v)); }
function strip_head($m){ return preg_replace("~<(span|div|p).*>.*</(span|div|p)>~ixs", '', $m[0]); }

?>