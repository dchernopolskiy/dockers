<?php
$relPath = '/usr/local/emhttp/plugins/dockerMan';

$allXmlDir = array(
	 'user' => '/boot/config/plugins/Docker', 
	 'built_in' => $relPath."/templates",
	 );

######################################
##   	  DOCKERCOMMON CLASS        ##
######################################
class DockerCommon {

	public function debugLog($var){
		echo "<pre>";
		print_r($var);
		echo "</pre>";
	}

	public function getTemplates(){
		global $allXmlDir;
		$out = array();
		foreach ($allXmlDir as $key => $dir) {
			$Files = scandir($dir);
			natcasesort($Files);
			if( count($Files) > 2 ) { 
				foreach( $Files as $file ) {
					if( $file != '.' && $file != '..') {
						$ext = new SplFileInfo($file);
						$ext = $ext->getExtension(); 
						if($ext == "xml"){
							$out[] = array('type' => $key,
								'path' => $dir.'/'.$file,
								'name' => preg_replace("/\.{$ext}/", '', $file),
								);
						}
					}
				}
			}
		}
		return $out;
	}
}

######################################
##   	  DOCKERUPDATE CLASS        ##
######################################
class DockerUpdate{

	public $updateFile = "/tmp/dockerUpdateStatus.json";


	public function download_url($url){
		return shell_exec("curl -s -k -L $url" );
	}


	public function getRemoteHash($Registry){
		return $this->getRegistryHash($Registry);
	}


	public function getLocalHash($file){
		if(is_file($file)){
			$doc = new DOMDocument();
			$doc->load($file);
			if ( ! $doc->getElementsByTagName( "Version" )->length == 0 ) {
				return $doc->getElementsByTagName( "Version" )->item(0)->nodeValue;
			} else {
				return NULL;
			}
		}

	}


	public function getGitHubHash($Repository){

		$github_url = $this->getTemplateGitHub($Repository);
		$auth = '-u 3a70e84248e28d64e0e837ebeb99a8597be3e08a:x-oauth-basic';

		$githubJsonUrl = preg_replace("/.*github.com\/(.*)/i", "${auth} https://api.github.com/repos/$1", $github_url);
		if (preg_match("/\/tree\//", $githubJsonUrl)){
			$githubJsonUrl = preg_replace("/\/tree\//","/branches/",$githubJsonUrl);
		} else {
			$githubJsonUrl .= "/commits?per_page=1";
		}

		$gitHubJson = json_decode($this->download_url($githubJsonUrl), TRUE);

		if (isset($gitHubJson[0]["sha"])) {
			return $gitHubJson[0]["sha"];
		} else if (isset($gitHubJson["commit"]["sha"])) {
			return $gitHubJson["commit"]["sha"];
		} else {
			return NULL;
		}
	}


	public function getRegistryHash($Repository){

		$DokerRegistry = $this->getTemplateRegistry($Repository);

		$RegistryContent = $this->download_url($DokerRegistry);
		$regex = "<a href=\".*(/builds_history/[^\"]*)\">Build Details</a>";
		preg_match("%$regex%", $RegistryContent, $matches);
		$buildDetailsUrl = $DokerRegistry . $matches[1];

		$buildDetails = $this->download_url($buildDetailsUrl);
		#$regex = "<td><a href=[\'\"]?.*?/build_id/\d+/code/[^>]*>([^<]*)</a></td><td>([^<]*)</td>";
		$regex = "<td>([^<]{15,})</td><td>([^<]*)</td>";
		preg_match_all("%$regex%", $buildDetails, $matches);

		$buildId = "";
		for ($i = 0; $i < count($matches[1]); $i++){
			$buildId = $matches[1][$i];
			$buildStatus = $matches[2][$i];
			if($buildStatus == "Finished"){
				break;
			}
		}

		if($buildId){
			return $buildId;
		} else {
			return NULL;
		}
	}


	public function getTemplateGitHub($Repository){
		$DockerCommon = new DockerCommon();
		foreach ($DockerCommon->getTemplates() as $file) {
			if ($file['type'] == 'built_in'){
				$doc = new DOMDocument();
				$doc->load($file['path']);
				$TemplateRepository = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
				$Repository = preg_replace("/:[\w]*$/i", "", $Repository);
				$TemplateRepository = preg_replace("/:[\w]*$/i", "", $TemplateRepository);
				if ( $Repository == $TemplateRepository ) {
					return trim($doc->getElementsByTagName( "GitHub" )->item(0)->nodeValue);
					break;
				}
			}
		}

	}


	public function getTemplateRegistry($Repository){
		$DockerCommon = new DockerCommon();
		foreach ($DockerCommon->getTemplates() as $file) {
			if ($file['type'] == 'built_in'){
				$doc = new DOMDocument();
				$doc->load($file['path']);
				$TemplateRepository = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
				$Repository = preg_replace("/:[\w]*$/i", "", $Repository);
				$TemplateRepository = preg_replace("/:[\w]*$/i", "", $TemplateRepository);
				if ( $Repository == $TemplateRepository ) {
					return trim($doc->getElementsByTagName( "Registry" )->item(0)->nodeValue);
					break;
				}
			}
		}

	}


	public function reloadUpdateStatus($Container = NULL){
		global $allXmlDir;
		if (is_file($this->updateFile)) {
			$Updates = json_decode( file_get_contents($this->updateFile), TRUE );
		} else {
			$Updates = array();
		}
		$Updates["Created"] = time();

		if(! $Container){
			$DockerClient = new DockerClient();
			$Containers = $DockerClient->getDockerContainers();
			if (! $Containers){ return NULL;}
		} else {
			$Containers = array($Container);
		}
		foreach ($Containers as $container) {
			$Name = $container['Name'];
			// $Image = explode(":", $container["Image"])[0];
			$Repository = $container["Image"];
			$RemoteVersion = $this->getRemoteHash($Repository);

			$file = sprintf('%s/my-%s.xml', $allXmlDir['user'], $container["Name"]);
			if (is_file($file)){
				$LocalVersion = $this->getLocalHash($file);
			} else {
				continue;
			}
			if ($LocalVersion) {
				if ($RemoteVersion == $LocalVersion){
					$update = "TRUE";
				} else {
					$update = "FALSE";
				}
			} else {
				$update = "UNDEF";
			}
			$Updates[$Name] = $update;
			// printf("Name[%s], Image[%s], Local[%s], Remote[%s], Update[%s]\n", $Name, $Repository, $LocalVersion, $RemoteVersion, $update);
		}
		file_put_contents($this->updateFile, json_encode($Updates));
	}


	public function updateStatus($Name){
		if (! is_file($this->updateFile)){
			$this->reloadUpdateStatus();
		}
		$Updates = json_decode( file_get_contents($this->updateFile), TRUE );
		$timeLapse = time() - $Updates["Created"];
		unset($Updates["Created"]);

		if ($timeLapse > 1800){
			$this->reloadUpdateStatus();
		}

		foreach ($Updates as $key => $value) {
			if ($Name == $key){
				return $value;
			}
		}
	}

}

######################################
##   	  DOCKERCLIENT CLASS        ##
######################################
class DockerClient {

	private function humanTiming ($time){
		$time = time() - $time; // to get the time since that moment
		$tokens = array (
			31536000 => 'year',
			2592000 => 'month',
			604800 => 'week',
			86400 => 'day',
			3600 => 'hour',
			60 => 'minute',
			1 => 'second'
		);
		foreach ($tokens as $unit => $text) {
			if ($time < $unit) continue;
			$numberOfUnits = floor($time / $unit);
			return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'')." ago";
		}
	}

	private function unchunk($result) {
		return preg_replace_callback(
			'/(?:(?:\r\n|\n)|^)([0-9A-F]+)(?:\r\n|\n){1,2}(.*?)'
			.'((?:\r\n|\n)(?:[0-9A-F]+(?:\r\n|\n))|$)/si',
			create_function('$matches','return hexdec($matches[1]) == strlen($matches[2]) ?
			$matches[2] :$matches[0];'),
			$result
		);
	}

	private function formatBytes($size){
		if ($size == 0){ return "0 B";}
		$base = log($size) / log(1024);
		$suffix = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
		return round(pow(1024, $base - floor($base)), 1) ." ". $suffix[floor($base)];
	}


	private function getDockerJSON($url){
		$fp = stream_socket_client('unix:///var/run/docker.sock', $errno, $errstr);

		if ($fp === false) {
			echo "Couldn't create socket: [$errno] $errstr";
			return NULL;
		}
		$out="GET {$url} HTTP/1.1\r\nConnection: Close\r\n\r\n";
		fwrite($fp, $out);
		while (!feof($fp)) {
			$data .= fgets($fp, 5000);
		}
		fclose($fp);
		$data = $this->unchunk($data);
		preg_match_all('/[^\{]*(\{.*\})/',$data, $matches);
		$json = array();
		foreach($matches[1] as $x){
			$json[] = json_decode( $x, true );
		}
		return $json;
	}

	private function postDockerJSON($url){
		$fp = stream_socket_client('unix:///var/run/docker.sock', $errno, $errstr);
		$out="POST {$url} HTTP/1.1\r\nConnection: Close\r\n\r\n";
		fwrite($fp, $out);
		$id = '';
		$oldpercentage = '';
		while (!feof($fp)) {
			$o = fgets($fp, 5000);
			$data .= $o;
			$js = json_decode( $o, true );
			if (is_array($js)) {
				$nid = $js['id'];
				if ($id != $nid){
					$id = $nid;
					echo "<br>$id: " . $js['status'] . '<br>';
				}
				if (array_key_exists('progressDetail', $js)){
					if ($js['progressDetail']['total']){
						$percentage = round(($js['progressDetail']['current'] / $js['progressDetail']['total']) * 100);
						if ($percentage % 10 == 0) {
							$percentage = "$percentage% ";
						} else {
							$percentage = '';
						}
						if ( $percentage != $oldpercentage){
							echo "$percentage";
						}
						$oldpercentage = $percentage;
					}
				}
			}
		}
		fclose($fp);
		$data = $this->unchunk($data);
		preg_match_all('/[^\{]*(\{.*\})/',$data, $matches);
		$json = array();
		foreach($matches[1] as $x){
			$json[] = json_decode( $x, true );
		}
		return $json;
	}

	private function getContainerDetails($id){
		$json = $this->getDockerJSON("/containers/{$id}/json");
		return $json;
	}

	public function getDockerContainers(){
		$containers = array();
		$json = $this->getDockerJSON("/containers/json?all=1");

		if (! $json){
			return NULL;
		}

		foreach($json as $obj){
			$c = array();
			$details = $this->getContainerDetails($obj['Id']);
			$status  = $obj['Status'] ? $obj['Status'] : "None";
			preg_match("/\b^Up\b/", $status, $matches);
			$running = $matches ? TRUE : FALSE;

			$c["Image"]   = $obj['Image'];
			$c["Name"]    = substr($obj['Names'][0], 1);
			$c["Status"]  = $status;
			$c["Running"] = $running;
			$c["Cmd"]     = $obj['Command'];
			$c["Id"]      = substr($obj['Id'],0,12);
			$c['Volumes'] = $details[0]["HostConfig"]['Binds'];
			$c["Created"] = $this->humanTiming($obj['Created']);
			$c["Ports"]   = $obj['Ports'];
			
			$containers[] = $c;
		}
		return $containers;
	}

	public function pullImage($image){
		$in = "/images/" . addslashes($image) . "/pull";
		$in = "/images/create?fromImage=$image";
		$out = $this->postDockerJSON($in);
		debugLog($out);

	}

	public function getDockerImages(){

		$images = array();
		$c = array();
		$json = $this->getDockerJSON("/images/json?all=0");

		if (! $json){
			return NULL;
		}

		foreach($json as $obj){
			$c = array();
			$tags = array();
			foreach($obj['RepoTags'] as $t){
				$tags[] = htmlentities($t);
			}
			
			$c["Created"]      = $this->humanTiming($obj['Created']);//date('Y-m-d H:i:s', $obj['Created']);
			$c["Id"]           = substr($obj['Id'],0,12);
			$c["ParentId"]     = substr($obj['ParentId'],0,12);
			$c["Size"]         = $this->formatBytes($obj['Size']);
			$c["VirtualSize"]  = $this->formatBytes($obj['VirtualSize']);
			$c["Tags"]         = $tags;

			$images[]          = $c;
		}
		return $images;
	}

}
?>