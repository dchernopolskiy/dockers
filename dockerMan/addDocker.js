
var pathNum = 2;
var portNum = 0;
var varNum = 0;
var currentPath = "/mnt/";
var brOpen = [];

if (!String.prototype.format) {
  String.prototype.format = function() {
    var args = arguments;
    return this.replace(/{(\d+)}/g, function(match, number) { 
      return typeof args[number] != 'undefined'
        ? args[number]
        : match
      ;
    });
  };
}


$(document).ready(function() {
	// $("#filePath").click(function() {
	// 	if (brOpen){
	// 		hideBrowser();brOpen = false;
	// 	}else{
	// 		showBrowser();brOpen = true;
	// 	}
	// });
	if ($("#NetworkType").val() != 'bridge') {
		$("#titlePort").css({'display': "none"});
	};
	$("#NetworkType").change(function() {
		if ($(this).val() != "bridge" ){
			$("#titlePort").css({'display': "none"});
		}else{
			$("#titlePort").css({'display': "block"});
			
		}
	});
	$("#TemplateSelect").change(function() {
		if ($(this).val() != "" ){
		document.getElementById("#xmlTemplate").value = $(this).val();
		document.forms["formTemplate"].submit();
	}
	});
});

function toggleBrowser(N) {
	if(typeof brOpen[N] == 'undefined') {
		brOpen[N] = false;
	}
	if (brOpen[N] == false) {
		brOpen[N] = true;
		$("#fileTree" + N).css({
			'display': "block"
		});
		$('#fileTree' + N).fileTree({
			root: currentPath,
			script: '/plugins/vendor/jsFileTree/jqueryFileTree.php',
			folderEvent: 'click',
			expandSpeed: 750,
			collapseSpeed: 750,
			multiFolder: false,
		}, function(file) {
			document.getElementById("hostPath" + N).value = file;
		});
	}else{
		$("#fileTree" + N).css({
			'display': "none"
		});
		$("#fileTree" + N).html("");
		brOpen[N] = false;
	}
}

function hideBrowser(N) {
	$("#fileTree" + N).css({
		'display': "none"
	});
	$("#fileTree" + N).html("");
	brOpen[N] = false;
}

function addPort(frm) {
	portNum++;
var row ='<tr id="portNum'+portNum+'"><td><input type="text" name="hostPort[]" value="'+frm.add_hostPort.value+'" class="textPort"></td> <td><input type="text" name="containerPort[]" value="'+ frm.add_containerPort.value+'" class="textPort"><input type="button" value="Remove" onclick="removePort(' + portNum + ');"></td></td>';
	jQuery('#portRows').append(row);
	frm.add_hostPort.value = '';
	frm.add_containerPort.value = '';
}

function removePort(rnum) {
	jQuery('#portNum' + rnum).remove();
}

function addPath(frm) {
	pathNum++;
	var hostPath = $("#hostPath1");
	var containerPath = $("#containerPath1");
	var row = '<tr id="pathNum{0}"><td><input type="text" id="hostPath{0}" name="hostPath[]" value="{1}" class="textPath"  onclick="toggleBrowser({0});"/>'+
				'<br><div id="fileTree{0}" class="fileTree"></div></td><td><input type="text" name="containerPath[]" value="{2}" class="textPath" '+
				'onclick="hideBrowser({0});"><input type="button" value="Remove" onclick="removePath({0});"></td></tr>';
	$('#pathRows tbody').append(row.format(pathNum, hostPath.val(), containerPath.val()));
	hostPath.val('');
	containerPath.val('');
}

function removePath(rnum) {
	jQuery('#pathNum' + rnum).remove();
}

function addEnv(frm) {
	varNum++;
var row ='<tr id="varNum'+varNum+'"><td><input type="text" name="VariableName[]" value="'+frm.add_VariableName.value+'" class="textEnv"></td> <td><input type="text" name="VariableValue[]" value="'+ frm.add_VariableValue.value+'" class="textEnv"><input type="button" value="Remove" onclick="removeEnv(' + varNum + ');"></td></td>';
	jQuery('#envRows tbody').append(row);
	frm.add_VariableName.value = '';
	frm.add_VariableValue.value = '';
}

function removeEnv(rnum) {
	jQuery('#varNum' + rnum).remove();
}
