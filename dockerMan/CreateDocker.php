<?
include_once("/usr/local/emhttp/plugins/dockerMan/DockerClient.php");

$DockerCommon = new DockerCommon();
$DockerUpdate = new DockerUpdate();

function prepareDir($dir){
	if (strlen($dir)){
		if ( ! is_dir($dir) && ! is_file($dir)){
			mkdir($dir, 0777, true);
			chown($dir, 'nobody');
			chgrp($dir, 'users');
			sleep(1);
		}
	}
}

function ContainerExist($container){

	$docker = new DockerClient();
	$all_containers = $docker->getDockerContainers();
	foreach ($all_containers as $ct) {
		if ($ct['Name'] == $container){
			return True;
			break;
		}
	}
	return False;
}

function xmlToCommand($xmlFile){
    $doc = new DOMDocument();
    $doc->loadXML($xmlFile);

	$Name          = $doc->getElementsByTagName( "Name" )->item(0)->nodeValue;
	$cmdName       = (strlen($Name)) ? '--name="' . $Name . '"' : "";
	$Privileged    = $doc->getElementsByTagName( "Privileged" )->item(0)->nodeValue;
	$cmdPrivileged = (strtolower($Privileged) == 'true') ?  '--privileged="true"' : "";
	$Repository    = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
	$Mode          = $doc->getElementsByTagName( "Mode" )->item(0)->nodeValue;
	$cmdMode       = '--net="'.strtolower($Mode).'"';
	$BindTime      = $doc->getElementsByTagName( "BindTime" )->item(0)->nodeValue;
	$cmdBindTime   = (strtolower($BindTime) == "true") ? '"/etc/localtime":"/etc/localtime":ro' : '';

	$Ports = array('');
	foreach($doc->getElementsByTagName('Port') as $port){
		$ContainerPort = $port->getElementsByTagName( "ContainerPort" )->item(0)->nodeValue;
		if (! strlen($ContainerPort)){ continue; }
		$HostPort      = $port->getElementsByTagName( "HostPort" )->item(0)->nodeValue;
		$Protocol      = $port->getElementsByTagName( "Protocol" )->item(0)->nodeValue;
		$Ports[]       = sprintf("%s:%s/%s", $HostPort, $ContainerPort, $Protocol);
	}

	$Volumes = array('');
	foreach($doc->getElementsByTagName('Volume') as $volume){
		$ContainerDir = $volume->getElementsByTagName( "ContainerDir" )->item(0)->nodeValue;
		if (! strlen($ContainerDir)){ continue; }
		$HostDir      = $volume->getElementsByTagName( "HostDir" )->item(0)->nodeValue;
		$DirMode      = $volume->getElementsByTagName( "Mode" )->item(0)->nodeValue;
		$Volumes[]    = sprintf( '"%s":"%s":%s', $HostDir, $ContainerDir, $DirMode);
	}

	if (strlen($cmdBindTime)) {
		$Volumes[] = $cmdBindTime;
	} 

	$Variables = array('');
	foreach($doc->getElementsByTagName('Variable') as $variable){
		$VariableName  = $variable->getElementsByTagName( "Name" )->item(0)->nodeValue;
		if (! strlen($VariableName)){ continue; }
		$VariableValue = $variable->getElementsByTagName( "Value" )->item(0)->nodeValue;
		$Variables[]   = sprintf('%s="%s"', $VariableName, $VariableValue);
	}

	$cmd = sprintf('/usr/bin/docker run -d %s %s %s %s %s %s %s', $cmdName, $cmdMode, $cmdPrivileged, implode(' -e ', $Variables), 
		   implode(' -p ', $Ports), implode(' -v ', $Volumes), $Repository);
	$cmd = preg_replace('/\s+/', ' ', $cmd);

	return array($cmd, $Name, $Repository);


}

function postToXML($post, $setOwnership = FALSE){
	$doc = new DOMDocument('1.0', 'utf-8');
	$doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
	$root = $doc->createElement('Container');
	$root = $doc->appendChild($root);

	$docName       = $root->appendChild($doc->createElement('Name'));
	$docRepository = $root->appendChild($doc->createElement('Repository'));
	$BindTime      = $root->appendChild($doc->createElement('BindTime'));
	$Privileged    = $root->appendChild($doc->createElement('Privileged'));
	$Environment   = $root->appendChild($doc->createElement('Environment'));
	$docNetworking = $root->appendChild($doc->createElement('Networking'));
	$Data          = $root->appendChild($doc->createElement('Data'));
	$Version       = $root->appendChild($doc->createElement('Version'));
	$Mode          = $docNetworking->appendChild($doc->createElement('Mode'));
	$Publish       = $docNetworking->appendChild($doc->createElement('Publish'));
	$Name          = preg_replace('/\s+/', '', $post["containerName"]);

	$docName->appendChild($doc->createTextNode(addslashes($Name)));
	$docRepository->appendChild($doc->createTextNode(addslashes($post["Repository"])));
	$BindTime->appendChild($doc->createTextNode((strtolower($post["BindTime"])     == 'on') ? 'true' : 'false'));
	$Privileged->appendChild($doc->createTextNode((strtolower($post["Privileged"]) == 'on') ? 'true' : 'false'));
	$Mode->appendChild($doc->createTextNode(strtolower($post["NetworkType"])));

    for ($i = 0; $i < count($post["hostPort"]); $i++){
    	if (! strlen($post["containerPort"][$i])) { continue;}
		$protocol      = $post["portProtocol"][$i];
		$Port          = $Publish->appendChild($doc->createElement('Port'));
		$HostPort      = $Port->appendChild($doc->createElement('HostPort'));
		$ContainerPort = $Port->appendChild($doc->createElement('ContainerPort'));
		$Protocol      = $Port->appendChild($doc->createElement('Protocol'));
     	$HostPort->appendChild($doc->createTextNode(trim($post["hostPort"][$i])));
     	$ContainerPort->appendChild($doc->createTextNode($post["containerPort"][$i]));
     	$Protocol->appendChild($doc->createTextNode($protocol));
    };

    for ($i = 0; $i < count($post["VariableName"]); $i++){
    	if (! strlen($post["VariableName"][$i])) { continue;}
		$Variable      = $Environment->appendChild($doc->createElement('Variable'));
		$VariableName  = $Variable->appendChild($doc->createElement('Name'));
		$VariableValue = $Variable->appendChild($doc->createElement('Value'));
    	$VariableName->appendChild($doc->createTextNode(addslashes(trim($post["VariableName"][$i]))));
    	$VariableValue->appendChild($doc->createTextNode(addslashes(trim($post["VariableValue"][$i]))));
    }

    for ($i = 0; $i < count($post["hostPath"]); $i++){
    	if (! strlen($post["hostPath"][$i])) {continue; }
    	if (! strlen($post["containerPath"][$i])) {continue; }
    	$tmpMode = $post["hostWritable"][$i];
    	if ($setOwnership){
    		prepareDir($post["hostPath"][$i]);
    	}
		$Volume       = $Data->appendChild($doc->createElement('Volume'));
		$HostDir      = $Volume->appendChild($doc->createElement('HostDir'));
		$ContainerDir = $Volume->appendChild($doc->createElement('ContainerDir'));
		$DirMode      = $Volume->appendChild($doc->createElement('Mode'));
		$HostDir->appendChild($doc->createTextNode(addslashes($post["hostPath"][$i])));
		$ContainerDir->appendChild($doc->createTextNode(addslashes($post["containerPath"][$i])));
		$DirMode->appendChild($doc->createTextNode($tmpMode));
    }

    $DockerUpdate = new DockerUpdate();
    $currentVersion = $DockerUpdate->getRemoteHASH($post["Repository"]);
    $Version->appendChild($doc->createTextNode($currentVersion));
    
    return $doc->saveXML();
}

if ($_POST){
	//debugLog($_POST);
    $postXML = postToXML($_POST, TRUE);
    // debugLog($postXML);

    // Get the command line 
    list($cmd, $Name, $Repository) = xmlToCommand($postXML);

    // Saving the generated configuration file.
    $xmlUserDir = $allXmlDir['user'];
    if(is_dir($xmlUserDir) === FALSE){
    	mkdir($xmlUserDir, 0777, true);
    }
    if(strlen($Name)) {
    	$filename = sprintf('%s/my-%s.xml', $xmlUserDir, $Name);
    	file_put_contents($filename, $postXML);
    }
    // Remove existing container
    if (ContainerExist($Name)){
    	$cmd = "/usr/bin/docker rm -f $Name;" . $cmd;
    }
    // Injecting the command in $_GET variable and loading the exec file.
    $_GET['cmd'] = $cmd;
    // echo $cmd; 
    include($relPath . "/execWithLog.php");


} else if ($_GET['updateContainer']){
	$Container = urldecode($_GET['updateContainer']);
	$isUpdatable = false;
	$getTemplates = new DockerCommon();
	foreach ($getTemplates->getTemplates() as $value) {
		if ($value['type'] == 'user'){

			$doc = new DOMDocument('1.0', 'utf-8');
			$doc->preserveWhiteSpace = false;
			$doc->load( $value['path'] );
			$doc->formatOutput = TRUE;

			$Name = $doc->getElementsByTagName( "Name" )->item(0)->nodeValue;
			$Repository = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;

			if ($Name == $Container) {

				$DockerUpdate     = new DockerUpdate();
				$CurrentVersion = $DockerUpdate->getRemoteHASH($Repository);

				if ($CurrentVersion){
					if ( $doc->getElementsByTagName( "Version" )->length == 0 ) {
						$root    = $doc->getElementsByTagName( "Container" )->item(0);
						$Version = $root->appendChild($doc->createElement('Version'));
					} else {
						$Version = $doc->getElementsByTagName( "Version" )->item(0);
					}
					$Version->nodeValue = $CurrentVersion;	

					$xmlUserDir = $allXmlDir['user'];
					$filename = sprintf('%s/my-%s.xml', $xmlUserDir, $Name);
					file_put_contents($filename, $doc->saveXML());
				}

				list($cmd, $Name, $Repository) = xmlToCommand($doc->saveXML());
				$cmd = sprintf("/usr/bin/docker rm -f %s; /usr/bin/docker pull %s; %s", $Name, $Repository, $cmd);
				$isUpdatable = true;
				$_GET['cmd'] = $cmd;
				include($relPath . "/execWithLog.php");
				$ct = array(
					'Name' => $Name, 
					'Image' => $Repository
					);
				$DockerUpdate->reloadUpdateStatus($ct);
				break;
			}
		}
	}
	if (! $isUpdatable){
		echo 'Configuration not found. Was this container created using this plugin?';
	}

} else {
	if($_GET['rmTemplate']){
		unlink($_GET['rmTemplate']);

	} else if($_GET['xmlTemplate']){
		list($xmlType, $xmlTemplate) = split(':', urldecode($_GET['xmlTemplate']));
		if(is_file($xmlTemplate)){
			$doc = new DOMDocument();
			$doc->load($xmlTemplate);

			$templateName         = $doc->getElementsByTagName( "Name" )->item(0)->nodeValue;
			$templateDescription  = $doc->getElementsByTagName( "Description" )->item(0)->nodeValue;
			$Registry             = $doc->getElementsByTagName( "Registry" )->item(0)->nodeValue;
			$templatePrivileged   = (strtolower($doc->getElementsByTagName( "Privileged" )->item(0)->nodeValue) == 'true') ? 'checked' : "";
			$templateRepository   = $doc->getElementsByTagName( "Repository" )->item(0)->nodeValue;
			$templateMode         = $doc->getElementsByTagName( "Mode" )->item(0)->nodeValue;;
			$readonly             = ($xmlType == 'built_in') ? 'readonly="readonly"' : '';
			$disabled             = ($xmlType == 'built_in') ? 'disabled="disabled"' : '';
			$templateDescription = preg_replace('/\[/', '<', $templateDescription);
			$templateDescription = preg_replace('/\]/', '>', $templateDescription);

			$templatePorts = '';
			$row = '
					<tr id="portNum%s">
						<td>
							<input type="text" name="containerPort[]" value="%s" class="textPort" %s title="Set the port your app uses inside the container.">
						</td>
						<td>
							<input type="text" name="hostPort[]" value="%s" class="textPort" title="Set the port you use to interact with the app.">
						</td>
						<td>
							<select name="portProtocol[]">
								<option value="tcp">tcp</option>
								<option value="udp" %s>udp</option>
							</select>
						</td>
						<td>
							<input type="button" value="Remove" onclick="removePort(%s);" %s>
						</td>
					</tr>';

			$i = 1;
			foreach($doc->getElementsByTagName('Port') as $port){
				$j = $i + 100;
				$ContainerPort  = $port->getElementsByTagName( "ContainerPort" )->item(0)->nodeValue;
				if (! strlen($ContainerPort)){ continue; }
				$HostPort       = $port->getElementsByTagName( "HostPort" )->item(0)->nodeValue;
				$Protocol       = $port->getElementsByTagName( "Protocol" )->item(0)->nodeValue;
				$select = ($Protocol == 'udp') ? 'selected' : '';
				$templatePorts .= sprintf($row, $j, $ContainerPort, $readonly, $HostPort, $select, $j, $disabled);
				$i++;
				}

			$templateVolumes = '';
			$row = '
				<tr id="pathNum%s">
					<td>
						<input type="text" name="containerPath[]" value="%s" class="textPath" onclick="hideBrowser(%s);" %s title="The directory your app uses inside the container. Ex: /config">
					</td>
					<td>
						<input type="text" id="hostPath%s" name="hostPath[]" value="%s" class="textPath" onclick="toggleBrowser(%s);" title="The directory in your array the app have access to. Ex: /mnt/user/Movies"/>
						<div id="fileTree%s" class="fileTree"></div>
					</td>
					<td>
						<select name="hostWritable[]">
							<option value="rw">Read/Write</option>
							<option value="ro" %s>Read Only</option>
						</select>
					</td>
					<td>
						<input type="button" value="Remove" onclick="removePath(%s);" %s />
					</td>
				</tr>';

			$i = 1;
			foreach($doc->getElementsByTagName('Volume') as $volume){
				$j = $i + 100;
				$ContainerDir     = $volume->getElementsByTagName( "ContainerDir" )->item(0)->nodeValue;
				if (! strlen($ContainerDir)){ continue; }
				$HostDir          = $volume->getElementsByTagName( "HostDir" )->item(0)->nodeValue;
				$Mode             = $volume->getElementsByTagName( "Mode" )->item(0)->nodeValue;
				$Mode             = ($Mode == "ro") ? "selected" : '';
				$templateVolumes .= sprintf($row, $j, $ContainerDir, $j, $readonly, $j, $HostDir, $j, $j, $Mode, $j, $disabled);
				$i++;
			}

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

			$i = 1;
			foreach($doc->getElementsByTagName('Variable') as $variable){
				$j = $i + 100;
				$VariableName       = $variable->getElementsByTagName( "Name" )->item(0)->nodeValue;
				if (! strlen($VariableName)){ continue; }
				$VariableValue      = $variable->getElementsByTagName( "Value" )->item(0)->nodeValue;
				$templateVariables .= sprintf($row, $j, $VariableName, $readonly, $VariableValue, $j, $disabled);
				$i++;
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
		width: 750px; 
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
		width: 700px;
	}
	table.portRows{
		width: 400px;
	}
	table.envTab{
		width: 600px;
	}
	table.Preferences{
		width: 100%;
	}
	table td {
		font-size: 14px;
		vertical-align: bottom;
		text-align: left;
	} 
	.desc {
		background: #FFF;
		border: 1px solid #dcdcdc;
		padding: 2px 6px;
		line-height: 20px;
		outline: none;
		-webkit-box-shadow: inset 2px 2px 6px #eef0f0;
		-moz-box-shadow: inset 2px 2px 6px #eef0f0;
		box-shadow: inset 2px 2px 6px #eef0f0;
		margin-top:0;
		margin-right: 10px;
	}
</style>
<form method="GET" id="formTemplate">
	<input type="hidden" id="#xmlTemplate" name="xmlTemplate" value="" />
	<input type="hidden" id="#rmTemplate" name="rmTemplate" value="" />
</form>

<div id="canvas" class="canvas" style="z-index:1;">

	<div id="title">
		<span class="left"><img src="/plugins/webGui/icons/default.png" class="icon" width="16" height="16">Preferences:</span>
	</div>

	<form method="post">
		<table class="Preferences">
			<tr>
				<td style="width: 150px;">Template:</td>
				<td >
					<select id="TemplateSelect" size="1">
						<option value="" selected>Select a template</option>
						<? 
						$rmadd = '';
						$getTemplates = new DockerCommon();
						foreach ($getTemplates->getTemplates() as $value) { 
							$selected = (isset($xmlTemplate) && $value['path'] == $xmlTemplate) ? ' selected ' : '';
							if (strlen($selected) && $value['type'] == 'user' ){ $rmadd = $value['path']; }
							echo "\t\t\t\t\t\t<option value=\"" . $value['type'] . ":" . $value['path'] . "\" {$selected} >" . $value['name'] . "</option>\n";
						};
						?>
					</select>
					<? if (strlen($rmadd)) {
						echo "<a onclick=\"rmTemplate('$rmadd');\" style=\"cursor:pointer;\"><img src=\"/plugins/dockerMan/remove.png\" title=\"$rmadd\" width=\"30px\"></a>";
					};?>
				</td>
			</tr>
			<?if(isset($templateDescription) && strlen($templateDescription)){?>
			<tr>
				<td style="vertical-align: top;">
					Description:
				</td>
				<td>
					<div class="desc">
						<?
						echo $templateDescription;
						if(isset($Registry)){
							echo "<br><br>Container Page: <a href=\"{$Registry}\" target=\"_blank\">{$Registry}</a>";
						}
						?>
					</div> 
				</td>
			</tr>
			<?};?>
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
			<span class="left"><img src="/plugins/webGui/icons/disksettings.png" class="icon" width="16" height="16">Paths</span>
		</div>

		<table id="pathRows" class="pathTab">
			<thead>
				<tr>
					<td>Container volume:</td>
					<td>Host path:</td>
					<td>Mode:</td>
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
					</td>
					<td>
						<select id="hostWritable1" name="hostWritable[]">
							<option value="rw" selected="selected">Read/Write</option>
							<option value="ro">Read Only</option>
						</select>
					</td>
					<td>
						<input onclick="addPath(this.form);" type="button" value="Add Path" class="btn">
					</td>
				</tr>
				<?if(isset($templateVolumes)){echo $templateVolumes;}?> 
			</tbody>
		</table>
		<div id="titlePort">
			<div id="title">
				<span class="left"><img src="/plugins/webGui/icons/network.png" class="icon" width="16" height="16">Ports</span>
			</div>

			<table id="portRows" class="portRows">
				<tbody>
					<tr>
						<td>Container port:</td>
						<td>Host port:</td>
						<td>Protocol:</td>
					</tr>

					<tr>
						<td>
							<input type="text" id="containerPort1" name="containerPort[]" class="textPort" title="Set the port your app uses inside the container.">
						</td>
						<td>
							<input type="text" id="hostPort1" name="hostPort[]" class="textPort" title="Set the port you use to interact with the app.">
						</td>
						<td>
							<select id="portProtocol1" name="portProtocol[]">
								<option value="tcp" selected="selected">tcp</option>
								<option value="udp">udp</option>
							</select>
						</td>
						<td>
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

		<table id="envRows" class="envTab">
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