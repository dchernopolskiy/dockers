
var pathNum = 0;
var portNum = 0;
brOpen = false;
$(document).ready(function() {
	$("#filePath").click(function() {
		if (brOpen){
			hideBrowser();brOpen = false;
		}else{
			showBrowser();brOpen = true;
		}
	});
	$("#networkType").change(function() {
		if ($(this).val() != "bridge" ){
			$("#titlePort").css({'display': "none"});
		}else{
			$("#titlePort").css({'display': "block"});
			
		}
	});
})

function showBrowser() {
	$("#fileTree").css({
		'display': "block"
	});
	$('#fileTree').fileTree({
		root: '/mnt/',
		script: '/plugins/vendor/jsFileTree/jqueryFileTree.php',
		folderEvent: 'click',
		expandSpeed: 750,
		collapseSpeed: 750,
		multiFolder: false
	}, function(file) {
		document.getElementById("filePath").value = file;
		brOpen = true;
	});
}

function hideBrowser() {
	$("#fileTree").css({
		'display': "none"
	});
	brOpen = false;
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
	var row = '<tr id="pathNum' + pathNum + '"><td><input type="text" name="hostPath[]" value="' + frm.add_hostPath.value + '" class="textPath"/></td><td><input type="text" name="containerPath[]" value="' + frm.add_containerPath.value + '" class="textPath"> <input type="button" value="Remove" onclick="removePath(' + pathNum + ');"></td></tr>';
	$('#pathRows tbody').append(row);
	frm.add_hostPath.value = '';
	frm.add_containerPath.value = '';
}

function removePath(rnum) {
	jQuery('#pathNum' + rnum).remove();
}
