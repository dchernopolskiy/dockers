<?php
function debugLog($var){
	echo "<pre>";
	print_r($var);
	echo "</pre>";
}

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