<?
include_once("/usr/local/emhttp/plugins/dockerMan/DockerClient.php");

$DockerUpdate = new DockerUpdate();

while ( TRUE ) {
	echo "Reloading status.";
	$DockerUpdate->reloadUpdateStatus();
	sleep(60);
}

?>