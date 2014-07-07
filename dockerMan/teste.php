<pre>
<?php

function serverIP(){
	preg_match_all("/\d:\s+([^\s+]*)\s+inet\s+([^\/]*).+/", shell_exec('ip -o addr') , $matches);
	unset($matches[0]);
	$IP = '';
	for ($i=0; $i < count($matches[1]); $i++) {
		if (in_array($matches[1][$i], array('br0','eth0'))) {
			$IP = $matches[2][$i];
		}
	}
	return $IP;
}

echo serverIP();

?>
</pre>


1: lo    inet 127.0.0.1/8 scope host lo\       valid_lft forever preferred_lft forever
7: br0    inet 192.168.0.100/24 brd 192.168.0.255 scope global br0\       valid_lft forever preferred_lft forever
8: docker0    inet 172.17.42.1/16 scope global docker0\       valid_lft forever preferred_lft forever
root@Servidor:/mnt/cache/Apps/owncloud# ip -o addr