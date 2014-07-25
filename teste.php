<?PHP

include("/usr/local/emhttp/plugins/dockerMan/DockerClient.php");
$docker = new DockerClient();
$docker->pullImage("needo/sickrage");
?>