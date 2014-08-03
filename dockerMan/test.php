<pre>
<?
include_once("/usr/local/emhttp/plugins/dockerMan/DockerClient.php");

function download_url($url){
		return shell_exec("curl -k -L $url" );
	}
function tidyHTML($buffer) {
    // load our document into a DOM object
    $dom = new DOMDocument();
    // we want nice output
    $dom->preserveWhiteSpace = false;
    $dom->loadHTML($buffer);
    $dom->formatOutput = true;
    return($dom->saveHTML());
}

$DockerUpdate = new DockerUpdate();
$DockerClient = new DockerClient();

$containers = $DockerClient->getDockerContainers();
$RemoteVersion = $DockerUpdate->getRemoteHASH("gfjardim/dropbox");

echo $RemoteVersion."\n";

$updateFile = "/tmp/dockerUpdateStatus.json";
#$DockerUpdate->reloadUpdateStatus();

$DokerRegistry = $DockerUpdate->getRegistryHash("gfjardim/nzbget");

print_r($DokerRegistry);

$DockerUpdate->reloadUpdateStatus();



function updateStatus($Name){
	global $DockerUpdate;
	global $updateFile;

	if (! is_file($updateFile)){
		echo "File not found. Creating... \n";
		$DockerUpdate->reloadUpdateStatus();
	}

	$Updates = json_decode( file_get_contents($updateFile), TRUE );
	$timeLapse = time() - $Updates["Created"];

	unset($Updates["Created"]);

	if ($timeLapse > 1800){
		echo "File too old. Rereating... \n";
		$DockerUpdate->reloadUpdateStatus();
	}

	foreach ($Updates as $key => $value) {
		printf("%s %s\n", $key, $value);
	}
}
updateStatus("");
?>
</pre>