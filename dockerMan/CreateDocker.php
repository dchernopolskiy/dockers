<?
if ($_POST){
	function prepare_dir($path){
		shell_exec('mkdir -p "{$path}"');
		shell_exec('chown -R nobody:users "{$path}"');
	}
    $networking = $_POST["networkType"];
    $repository = $_POST["repository"];
    $priviledged = $_POST["priviledged"];
    $name = $_POST["containerName"];
    $bindTime = $_POST["bindTime"];
    
    $ports = "";
    for ($i = 0; $i < count($_POST["hostPort"]); $i++){
        $ports .= "-p ".$_POST["hostPort"][$i].":".$_POST["containerPort"][$i]." "; 
    }
    $volumes = "";
    for ($i = 0; $i < count($_POST["hostPath"]); $i++){
    	prepare_dir($_POST["hostPath"][$i]);
        $volumes .= "-v ".$_POST["hostPath"][$i].":".$_POST["containerPath"][$i]." "; 
    }
    $cmd = "/usr/bin/docker run -d --name='".$name."' ";
    if ($priviledged == "on"){
        $cmd .= "--privileged ";}
    
    if($networking == "host"){
        $cmd .= "--net='host' ";}
        
    if($networking == "none"){
        $cmd .= "--net='none' ";}
    
    if ($networking == "bridge"){ 
        $cmd .= $ports;}
    if ($bindTime == "on"){
	    $cmd .= "-v /etc/localtime:/etc/localtime:ro ";
    }
    $cmd .= $volumes . $repository;
    $_GET['cmd'] = $cmd;
    include("/usr/local/emhttp/plugins/dockerMan/execWithLog.php");
} else {

?>

<link type="text/css" rel="stylesheet" href="/plugins/webGui/style/default_layout.css">
<style type="text/css">
body {
margin: 10px;
font-size: 14px;
}
.fileTree {
width: 240px;
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
input.textPort{
width: 100px;
}
table.pathTab{
width: 600px;
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
} 
</style>
<script src="/plugins/vendor/jquery/jquery-1.10.2.min.js" type="text/javascript">
</script>
<script src="/plugins/vendor/jsFileTree/jqueryFileTree.js" type="text/javascript"></script>
<link href="/plugins/vendor/jsFileTree/jqueryFileTree.css" rel="stylesheet" type="text/css" media="screen">
<script type="text/javascript" src="/plugins/dockerMan/addTree.js"> </script>

<div id="canvas" class="canvas" style="z-index:1;">
<form method="post">
<div id="title">
<span class="left"><img src="/plugins/dockerMan/dockerMan.png" class="icon" width="16" height="16">Preferences:</span>
</div>

<table class="Preferences">
<tr>
<td>Name:</td>

<td><input type="text" name="containerName" class="textPath"></td>
</tr>

<tr>
<td>Repository:</td>

<td><input type="text"name="repository" class="textPath"></td>
</tr>

<tr>
<td>Network type:</td>

<td><select id="networkType" name="networkType" size="1">
<option value='bridge' selected>
Bridge
</option>

<option value='host'>
Host
</option>

<option value='none'>
None
</option>
</select></td>
</tr>

<tr>
<td>Privileged:</td>

<td><input type="checkbox" name="priviledged"></td>
</tr>

<tr>
<td>Bind time:</td>

<td><input type="checkbox" name="bindTime" checked></td>
</tr>
</table>

<div id="title">
<span class="left"><img src="/plugins/dockerMan/dockerMan.png" class="icon" width="16" height="16">Paths</span>
</div>

<table id="pathRows" class="pathTab">
<thead>
<tr>
<td>Host path:</td>

<td>Container volume:</td>
</tr>
</thead>

<tbody>
<tr>
<td id="fBrowser">
<input type="text" id="filePath" name="add_hostPath" class="textPath" autocomplete="off"><br>

<div id="fileTree" class="fileTree"></div>
</td>

<td><input type="text" name="add_containerPath" class="textPath"> <input onclick="addPath(this.form);" type="button" value="Add Path"></td>
</tr>
</tbody>
</table>
<div id="titlePort">
<div id="title">
<span class="left"><img src="/plugins/dockerMan/dockerMan.png" class="icon" width="16" height="16">Ports</span>
</div>

<table id="portRows" class="portRows">
<tbody>
<tr>
<td>Host port:</td>

<td>Container port:</td>
</tr>

<tr>
<td><input type="text" name="add_hostPort" class="textPort"></td>

<td><input type="text" name="add_containerPort" class="textPort"> <input onclick="addPort(this.form);" type="button" value="Add port"></td>
</tr>
</tbody>
</table>
</div>

<div style="text-align:right;"><input type="submit" value="Add" style="font-weight: bold; font-size: 16px;"></div>
</form>
</div>
</body>
</html>
<?};?>