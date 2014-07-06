<?
$relPath = '/usr/local/emhttp/plugins/dockerMan';

$allXmlDir = array(
	 'user' => '/boot/config/plugins/Docker', 
	 'built_in' => $relPath."/templates",
	 );

function debugLog($var){
	echo "<pre>";
	print_r($var);
	echo "</pre>";
}

function getTemplates(){
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

function prepareDir($dir){
	if (strlen($dir)){
		if (!is_dir($dir)){
			echo "Setting the ownership to nobody.";
			mkdir($dir);
		// shell_exec('/usr/bin/chmod 770 "$dir"');
		// shell_exec('/usr/bin/chown -R 99:100 $dir');
			chown($dir, 'nobody');
			chgrp($dir, 'users');
			sleep(1);
		// exec('/usr/bin/chmod 770 "$dir"', $out, $retr_val);
		// exec('/usr/bin/chown -R nobody:user "$dir"', $out, $retr_val);
		}
	}
}

function xmlToVariables($xmlfile){
    $xml = new SimpleXMLElement($xmlfile);

    $Name = $xml->Name;
    $Repository = $xml->Repository.'';
    $Privileged = $xml->Privileged.'';
    $Mode = $xml->Networking->Mode.'';
    $BindTime = (strtolower($xml->BindTime) == 'true') ? TRUE : FALSE ;

    $Ports = array('');
    foreach($xml->Networking->Publish->Port as $p){
        if ($p->ContainerPort == ""){continue;}
        $p = $p->HostPort.':'.$p->ContainerPort.'/'.$p->Protocol;
        $Ports[] = preg_replace('/\s/', '', $p);
    }

    $Variables = array('');
    foreach($xml->Environment->Variable as $v){
        if ($v->Name == ""){continue;}
        $Variables[] = trim($v->Name).'="'.$v->Value.'"';
    }

    $Volumes = array('');
    foreach($xml->Data->Volume as $v){
        if ($v->HostDir == "" && $v->ContainerDir == ""){continue;}
        $op = (strtolower($v->Mode) == 'ro') ? 'ro' : 'rw'; 
        $Volumes[] = '"'.$v->HostDir.'":"'.$v->ContainerDir.'":'.$op;
    }

    $out = array(
        'Name' => $Name,
        'Ports' => $Ports,
        'Repository' => $Repository,
        'Privileged' => $Privileged,
        'Mode' => $Mode,
        'BindTime' => $BindTime,
        'Variables' => $Variables,
        'Volumes' => $Volumes,
        );

    return $out;
};

function postToXML($post, $setOwnership = FALSE){
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
	$root = $doc->createElement('Container');
	$root = $doc->appendChild($root);

	$docName = $root->appendChild($doc->createElement('Name'));
	$docName->appendChild($doc->createTextNode(addslashes($post["containerName"])));

	$docRepository = $root->appendChild($doc->createElement('Repository'));
	$docRepository->appendChild($doc->createTextNode(addslashes($post["Repository"])));

	$BindTime = $root->appendChild($doc->createElement('BindTime'));
	$BindTime->appendChild($doc->createTextNode((strtolower($post["BindTime"]) == 'on') ? 'true' : 'false'));

	$Privileged = $root->appendChild($doc->createElement('Privileged'));
	$Privileged->appendChild($doc->createTextNode((strtolower($post["Privileged"]) == 'on') ? 'true' : 'false'));

	$docNetworking = $root->appendChild($doc->createElement('Networking'));
	$Mode = $docNetworking->appendChild($doc->createElement('Mode'));
	$Mode->appendChild($doc->createTextNode(strtolower($post["NetworkType"])));

	$Publish = $docNetworking->appendChild($doc->createElement('Publish'));
    for ($i = 0; $i < count($post["hostPort"]); $i++){
    	$protocol = (strpos($post["containerPort"][$i], '/udp')) ? 'udp' : 'tcp';
    	$post["containerPort"][$i] = preg_replace('/\/tcp|\/udp/', '', $post["containerPort"][$i]);
    	$post["HostPort"][$i] = preg_replace('/\/[tcup]*/', '', $post["HostPort"][$i]);

    	$Port = $Publish->appendChild($doc->createElement('Port'));
    	$HostPort = $Port->appendChild($doc->createElement('HostPort'));
     	$ContainerPort = $Port->appendChild($doc->createElement('ContainerPort'));
     	$Protocol = $Port->appendChild($doc->createElement('Protocol'));
     	$HostPort->appendChild($doc->createTextNode(trim($post["hostPort"][$i])));
     	$ContainerPort->appendChild($doc->createTextNode($post["containerPort"][$i]));
     	$Protocol->appendChild($doc->createTextNode($protocol));
    };

	$Environment = $root->appendChild($doc->createElement('Environment'));
    for ($i = 0; $i < count($post["VariableName"]); $i++){
    	$Variable = $Environment->appendChild($doc->createElement('Variable'));
    	$n = $Variable->appendChild($doc->createElement('Name'));
    	$n->appendChild($doc->createTextNode(addslashes(trim($post["VariableName"][$i]))));
    	$v = $Variable->appendChild($doc->createElement('Value'));
    	$v->appendChild($doc->createTextNode(addslashes(trim($post["VariableValue"][$i]))));
    }

    $Data = $root->appendChild($doc->createElement('Data'));
    for ($i = 0; $i < count($post["hostPath"]); $i++){
    	if (! strlen($post["containerPath"][$i])){continue; }
    	$tmpMode = (strpos($post["containerPath"][$i], ':ro')) ? 'ro' : 'rw';
    	$post["containerPath"][$i] = preg_replace('/:[row]+$/', '', $post["containerPath"][$i]);
    	if ($setOwnership){
    		prepareDir($post["hostPath"][$i]);
    	}
    	$Volume = $Data->appendChild($doc->createElement('Volume'));
    	$HostDir = $Volume->appendChild($doc->createElement('HostDir'));
    	$HostDir->appendChild($doc->createTextNode(addslashes($post["hostPath"][$i])));
    	$ContainerDir = $Volume->appendChild($doc->createElement('ContainerDir'));
    	$ContainerDir->appendChild($doc->createTextNode(addslashes($post["containerPath"][$i])));
    	$Mode = $Volume->appendChild($doc->createElement('Mode'));
    	$Mode->appendChild($doc->createTextNode($tmpMode));
    }

    return $doc->saveXML();
}

if ($_POST){
	//debugLog($_POST);
    $postXML = postToXML($_POST, TRUE);
    $postArray = xmlToVariables($postXML);
    $xmlUserDir = $allXmlDir['user'];
    if(is_dir($xmlUserDir) === FALSE){
    	mkdir($xmlUserDir, 0777, true);
    }
    if(strlen($postArray['Name'])) {
    	file_put_contents($xmlUserDir.'/my-'.$postArray['Name'].'.xml', $postXML);
    }

    $postArray['Name'] = (strlen($postArray['Name'])) ? '--name="'.$postArray['Name'].'"' : "";
    $postArray['Privileged'] = (strtolower($postArray['Privileged']) == 'true') ? '--privileged="true"' : "";
    $postArray['Mode'] = '--net="'.strtolower($postArray['Mode']).'"';
	
	if($postArray['BindTime'] === TRUE){$postArray['Volumes'][] = '"/etc/localtime":"/etc/localtime":ro';}

	$cmd = sprintf('/usr/bin/docker run -d %s %s %s %s %s %s %s',$postArray['Name'], $postArray['Mode'], $postArray['Privileged'], implode(' -e ', $postArray['Variables']), 
		   implode(' -p ', $postArray['Ports']), implode(' -v ', $postArray['Volumes']), $postArray['Repository']);
	$cmd = preg_replace('/\s+/', ' ', $cmd);
    $_GET['cmd'] = $cmd;
/*     echo $cmd; */
    include("/usr/local/emhttp/plugins/dockerMan/execWithLog.php");

} else {
	if($_GET['xmlTemplate']){
		list($xmlType, $xmlTemplate) = split(':', urldecode($_GET['xmlTemplate']));
		if(is_file($xmlTemplate)){
			$doc = new DOMDocument();
			$doc->load($xmlTemplate);
			$fileXML = $doc->saveXML();
			$fileVariables = xmlToVariables($fileXML);
			$templateName = $fileVariables['Name'];
			$templatePrivileged = (strtolower($fileVariables['Privileged']) == 'true') ? 'checked' : "";
			$templateRepository = $fileVariables['Repository'];
			$templateMode = $fileVariables['Mode'];
			$readonly = ($xmlType == 'built_in') ? 'readonly="readonly"' : '';
			$disabled = ($xmlType == 'built_in') ? 'disabled="disabled"' : '';

			$Ports = $fileVariables['Ports'];
			$templatePorts = '';
			$row = '
					<tr id="portNum%s">
						<td>
							<input type="text" name="containerPort[]" value="%s" class="textPort" %s title="Set the port your app uses inside the container.">

						</td>
						<td>
							<input type="text" name="hostPort[]" value="%s" class="textPort" title="Set the port you use to interact with the app.">
							<input type="button" value="Remove" onclick="removePort(%s);" %s>
						</td>
					</tr>';

			for ($i=0; $i < count($Ports); $i++) { 
				if(strlen($Ports[$i])){
					$j = $i + 100;
					list($HostPort, $ContainerPort) = split(':', $Ports[$i], 2);
					$templatePorts .= sprintf($row, $j, $ContainerPort, $readonly, $HostPort, $j, $disabled);
				}
			}

			$Volumes = $fileVariables['Volumes'];
			$templateVolumes = '';
			$row = '
				<tr id="pathNum%s">
					<td>
						<input type="text" name="containerPath[]" value="%s" class="textPath" onclick="hideBrowser(%s);" %s title="The directory your app uses inside the container. Ex: /config">
					</td>
					<td>
						<input type="text" id="hostPath%s" name="hostPath[]" value="%s" class="textPath" onclick="toggleBrowser(%s);" title="The directory in your array the app have access to. Ex: /mnt/user/Movies"/>
						<div id="fileTree%s" class="fileTree"></div>
						<input type="button" value="Remove" onclick="removePath(%s);" %s />
					</td>
				</tr>';
			
			for ($i=0; $i < count($Volumes); $i++) { 
				if(strlen($Volumes[$i])){
					$j = $i + 100;
					list($HostDir, $ContainerDir) = split(':', preg_replace('/[\"]/', '', $Volumes[$i]), 2);
					$templateVolumes .= sprintf($row, $j, $ContainerDir, $j, $readonly, $j, $HostDir, $j, $j, $j, $disabled);
				}
			}

			$Vars = $fileVariables['Variables'];
			$templateVariables = '';
			$row = '
				<tr id="varNum%s">
					<td>
						<input type="text" name="VariableName[]" value="%s" class="textEnv" %s/>
					</td>
					<td>
						<input type="text" name="VariableValue[]" value="%s" class="textEnv">
						<input type="button" value="Remove" onclick="removeEnv(%s);" %s>
					</td>
				</tr>';

			for ($i=0; $i < count($Vars); $i++) { 
				if(strlen($Vars[$i])){
					$j = $i + 100;
					list($VarName, $VarValue) = split('=', preg_replace('/[\"]/', '', $Vars[$i]), 2);
					$templateVariables .= sprintf($row, $j, $VarName, $readonly, $VarValue, $j, $disabled);
				}
			}

		}

	}
?>
<script src="/plugins/vendor/jquery/jquery-1.10.2.min.js" type="text/javascript"></script>
<script src="/plugins/vendor/jsFileTree/jqueryFileTree.js" type="text/javascript"></script>
<link href="/plugins/vendor/jsFileTree/jqueryFileTree.css" rel="stylesheet" type="text/css" media="screen">
<script type="text/javascript" src="/plugins/dockerMan/addDocker.js"> </script>
<link type="text/css" rel="stylesheet" href="/plugins/webGui/style/default_layout.css">
<style type="text/css">
	body {
		margin: 10px;
		font-size: 14px;
	}
	.fileTree {
		width: 230px;
		height: 300px;
		border-top: solid 1px #BBB;
		border-left: solid 1px #BBB;
		border-bottom: solid 1px #BBB;
		border-right: solid 1px #BBB;
		background: #FFF;
		overflow: scroll;
		padding: 5px;
		position:absolute;
		z-index:100;
		display:none;
	};
	.canvas{
		background: #ffffff;
		width: 100%;
		height: 100%;
	}
	input.textPath{
		width: 240px;
	}
	input.textEnv{
		width: 230px;
	}
	input.textPort{
		width: 100px;
	}
	table.pathTab{
		width: 650px;
	}
	table.portRows{
		width: 320px;
	}
	table.Preferences{
		width: 100%;
	}
	table td {
		font-size: 14px;
		vertical-align: bottom;
		text-align: left;
	} 
</style>
<form method="GET" id="formTemplate">
	<input type="hidden" id="#xmlTemplate" name="xmlTemplate" value="" />
</form>

<div id="canvas" class="canvas" style="z-index:1;">


	<div id="title">
		<span class="left"><img src="/plugins/dockerMan/dockerMan.png" class="icon" width="16" height="16">Preferences:</span>
	</div>

	<form method="post">
		<table class="Preferences">
			<tr>
				<td>Template:</td>
				<td >
					<select id="TemplateSelect" size="1">
						<option value="" selected>Select a template</option>
						<? foreach (getTemplates() as $value) { 
							$selected = (isset($xmlTemplate) && $value['path'] == $xmlTemplate) ? ' selected ' : '';
						echo "\t\t\t\t\t\t<option value=\"" . $value['type'] . ":" . $value['path'] . "\" {$selected} >" . $value['name'] . "</option>\n";};?>
					</select>
				</td>
			</tr>
			<tr>
			<td></td>
				<td>
					
				</td>
			</tr>
			<tr>
				<td>Name:</td>

				<td><input type="text" name="containerName" class="textPath" value="<? if(isset($templateName)){ echo $templateName;} ?>"></td>
			</tr>

			<tr>
				<td>Repository:</td>

				<td><input type="text"name="Repository" class="textPath" value="<? if(isset($templateRepository)){ echo $templateRepository;} ?>"></td>
			</tr>

			<tr>
				<td>Network type:</td>

				<td><select id="NetworkType" name="NetworkType" size="1">
					<? foreach (array('bridge', 'host', 'none') as $value) {
						$selected = ($templateMode == $value) ? "selected" : "";
						echo "<option value=\"{$value}\" {$selected}>".ucwords($value)."</option>";
					}?>
				</select></td>
			</tr>

			<tr>
				<td>Privileged:</td>

				<td><input type="checkbox" name="Privileged" <?if(isset($templatePrivileged)) {echo $templatePrivileged;}?>></td>
			</tr>

			<tr>
				<td>Bind time:</td>

				<td><input type="checkbox" name="BindTime" checked></td>
			</tr>
		</table>

		<div id="title">
			<span class="left"><img src="/plugins/dockerMan/dockerMan.png" class="icon" width="16" height="16">Paths</span>
		</div>

		<table id="pathRows" class="pathTab">
			<thead>
				<tr>
					<td>Container volume:</td>
					<td>Host path:</td>
				</tr>
			</thead>

			<tbody>
				<tr>
					<td>
						<input type="text" id="containerPath1" name="containerPath[]" class="textPath" onfocus="hideBrowser(1);" title="The directory your app uses inside the container. Ex: /config"> 
					</td>
					<td>
						<input type="text" id="hostPath1" name="hostPath[]" class="textPath" autocomplete="off" onclick="toggleBrowser(1);" title="The directory in your array the app have access to. Ex: /mnt/user/Movies">
						<div id="fileTree1" class="fileTree"></div>
						<input onclick="addPath(this.form);" type="button" value="Add Path" class="btn">
					</td>
				</tr>
				<?if(isset($templateVolumes)){echo $templateVolumes;}?> 
			</tbody>
		</table>
		<div id="titlePort">
			<div id="title">
				<span class="left"><img src="/plugins/dockerMan/dockerMan.png" class="icon" width="16" height="16">Ports</span>
			</div>

			<table id="portRows" class="portRows">
				<tbody>
					<tr>
						<td>Container port:</td>
						<td>Host port:</td>
					</tr>

					<tr>
						<td>
							<input type="text" id="containerPort1" name="containerPort[]" class="textPort" title="Set the port your app uses inside the container.">
						</td>
						<td>
							<input type="text" id="hostPort1" name="hostPort[]" class="textPort" title="Set the port you use to interact with the app.">
							<input onclick="addPort(this.form);" type="button" value="Add port" class="btn">
						</td>
					</tr>
					<?if(isset($templatePorts)){echo $templatePorts;}?> 
				</tbody>
			</table>
		</div>

		<div id="title">
			<span class="left"><img src="/plugins/dockerMan/dockerMan.png" class="icon" width="16" height="16">Environment Variables</span>
		</div>

		<table id="envRows" class="pathTab">
			<thead>
				<tr>
					<td>Variable Name:</td>
					<td>Variable Value:</td>
				</tr>
			</thead>

			<tbody>
				<tr>
					<td>
						<input type="text" id="VariableName1" name="VariableName[]" class="textEnv">
					</td>
					<td>
						<input type="text" id="VariableValue1" name="VariableValue[]" class="textEnv"> 
						<input onclick="addEnv(this.form);" type="button" value="Add Variable">
					</td>
				</tr>
				<?if(isset($templateVariables)){echo $templateVariables;}?> 
			</tbody>
		</table>

		<div style="text-align:right;"><br><input type="submit" value="Add" style="font-weight: bold; font-size: 16px;"></div>
	</form>
</div>
<?};?>