<?php
// accept asynchronous grade postings, comment set updates, and comment set queries without HTML content

include "tools.php";
$noPre = true; // turn off <pre></pre> stuff
logInAs();
if (!$isstaff) die("page restricted to staff");

if (array_key_exists('checkoff', $_REQUEST)) {
    $grade = json_decode(file_get_contents("php://input"), true);
    if (!array_key_exists('slug', $grade) || !array_key_exists('student', $grade)) die ("grade payload missing required keys");
    $grade['timestamp'] = time();
    $grade['grader'] = $user;

    $student_entry = rosterEntry($grade['student']);
    if (!$student_entry) {
        die(json_encode(
            array(
                "success"=>False,
                "message"=>"invalid student $student_entry",
            )
        ));
    }

    $reqfile = "meta/requests/queued/$grade[slug]-$grade[student]";

    $payload = json_encode($grade); 
    if (!file_put("uploads/$grade[slug]/$grade[student]/.checkoff", $payload))
	die(json_encode(array("success"=>False,"message"=>"error recording checkoff (1)")));
    if (!file_put($reqfile, "1"))
	die(json_encode(array("success"=>False,"message"=>"error recording checkoff (2)")));
    if (!file_append("uploads/$grade[slug]/.checkofflog", "$payload\n"))
	die(json_encode(array("success"=>False,"message"=>"error recording checkoff (3)")));
    die(json_encode(array("success"=>True,"message"=>"")));

}
?>
﻿<!DOCTYPE html>
<html><head>
    <title><?=$metadata['title']?> Grading View</title>
    <link rel="stylesheet" href="display.css" type="text/css"></link>
    <style>
        .error:not(.big) { background-color: rgba(255,0,0,0.25); }
        .pending:not(.big) { background-color: rgba(255,255,0,0.25); }
        .success:not(.big) { background-color: rgba(0,255,0,0.25); }
    </style>
<script>
function ajax(payload, qstring, response=null) {

    /*/ console.log("### ajax ###", payload, qstring); /**/

    var xhr = new XMLHttpRequest();
    if (!("withCredentials" in xhr)) {
        alert('Your browser does not support TLS in XMLHttpRequests; please use a browser based on Gecko or Webkit'); return null;
    }
    xhr.open("POST", "<?=$_SERVER['SCRIPT_NAME']?>?"+qstring, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.withCredentials = true;
    xhr.onerror = function() {
        alert("Grading failed (network trouble or browser incompatibility)");
    }
    xhr.onload = function() {
        if (response) response(xhr.responseText);
        else console.info("<?=$_SERVER['SCRIPT_NAME']?>?"+qstring + ' returned ' + JSON.stringify(xhr.responseText));
    }
    xhr.send(JSON.stringify(payload));
}

function checkoff_response(id, response) {
    var root = document.getElementById(id)
    if (response == '') {
        response = {
            success: false,
            message: "empty response received from server",
        }
    } else {
        response = JSON.parse(response);
    }
    if (response.success) {
        root.classList.remove('pending');
        root.classList.add('success');
        document.getElementById(id + '|error').innerHTML = '';
        document.getElementById(id + '|student').disabled = true;
        document.getElementById(id + '|ratio').disabled = false;
        document.getElementById(id + '|comments').disabled = false;
        maybeNewCheckoff();
    }  else {
        root.classList.add('error')
        document.getElementById(id + '|error').innerHTML = response.message;
        document.getElementById(id + '|student').disabled = false;
        document.getElementById(id + '|ratio').disabled = false;
        document.getElementById(id + '|comments').disabled = false;
    }
}
    
function checkoff(id) {
    var ans = {
        checkoff:"yes",
        grader:"<?=$user?>",
        slug:"<?=$_GET["slug"]?>",
    }
    var root = document.getElementById(id)
    ans['student'] = document.getElementById(id + '|student').value;
    ans['ratio'] = document.getElementById(id + '|ratio').value;
    ans['comments'] = document.getElementById(id + '|comments').value;
    document.getElementById(id + '|student').disabled = true;
    document.getElementById(id + '|ratio').disabled = true;
    document.getElementById(id + '|comments').disabled = true;
    root.classList.remove('error');
    root.classList.remove('success');
    root.classList.add('pending');
    ajax(ans, 'checkoff=1', function(response) {checkoff_response(id, response)});
}

function changed(id) {
    var root = document.getElementById(id)
    root.classList.remove('success');
    root.classList.remove('error');
    root.classList.remove('pending');
}
function newCheckoff() {
    var table = document.getElementById('allCheckoffs')
    var id = 'checkoff|' + table.rows.length;
    var newRow = table.insertRow();
    newRow.setAttribute('id', id);
    var compid = document.createElement('input');
    compid.setAttribute('list', 'students-list');
    compid.setAttribute('type', 'text');
    compid.setAttribute('id', id + '|student');
    compid.setAttribute('onchange', 'changed("'+id+'")');
    var ratio = document.createElement('input');
    ratio.setAttribute('type', 'text');
    ratio.setAttribute('id', id + '|ratio');
    ratio.setAttribute('onchange', 'changed("'+id+'")');
    var comments = document.createElement('input');
    comments.setAttribute('type', 'text');
    comments.setAttribute('id', id + '|comments');
    comments.setAttribute('onchange', 'changed("'+id+'")');
    newRow.insertCell().appendChild(compid);
    newRow.insertCell().appendChild(ratio);
    newRow.insertCell().appendChild(comments);
    var submit = document.createElement('input')
    submit.setAttribute('type', 'submit');
    submit.setAttribute('value', 'record')
    submit.setAttribute('onclick', 'checkoff("' + id + '")');
    newRow.insertCell().appendChild(submit);
    newRow.insertCell().setAttribute('id', id + '|error');
    return id;
}
function oldCheckoff(json) {
    var id = newCheckoff();
    document.getElementById(id + '|student').value = json['student'];
    document.getElementById(id + '|student').disabled = true;
    document.getElementById(id + '|ratio').value = json['ratio'];
    document.getElementById(id + '|comments').value = json['comments'];
    document.getElementById(id).classList.add('success');
}
function maybeNewCheckoff() {
    var table = document.getElementById('allCheckoffs')
    var lastRow = table.rows.item(table.rows.length-1)
    if (table.rows.length == 1 || lastRow.classList.contains('success')) {
	newCheckoff();
    }
}
</script>
<?php
if (!array_key_exists('slug', $_GET)) {
?>
Assignments available for checkoff:
<ul>
<?php
foreach (assignments(true) as $slug=>$details) {
    if (array_key_exists('allow-checkoff', $details) && $details['allow-checkoff']) {
        echo('<li><a href="checkoff.php?slug='.$slug.'">'.$slug.'</a></li>');
    }
}
?>
<?php
} else {
$slug = $_GET['slug'];
if (!assignments(true)[$slug]['allow-checkoff']) {
    die("Checkoffs not enabled for this assignment");
}
$student_data= "<datalist id='students-list'>";
$found_checkoffs = array();
foreach(fullRoster() as $compid=>$details) {
    #if (array_key_exists('role', $details) && $details['role'] == 'Admin') continue;
    #if (!array_key_exists('role', $details) || $details['role'] != 'Student') continue;
    $student_data .= "<option value='$compid'>$details[name] ($compid)</option>";
    if (file_exists("uploads/$slug/$compid/.checkoff")) {
	$checkoff = json_decode(file_get_contents("uploads/$slug/$compid/.checkoff"), JSON_OBJECT_AS_ARRAY);
	if (array_key_exists('grader', $checkoff) && $checkoff['grader'] == $user) {
	    $found_checkoffs[] = $checkoff;
	}
    }
}
$student_data.= "</datalist>";
?>
<script>
function setup() {
<?php
    foreach ($found_checkoffs as $checkoff) {
	echo 'oldCheckoff('.json_encode($checkoff).');';
    }
?>
    maybeNewCheckoff();
}
</script>
<link rel="stylesheet" href="display.css" type="text/css"></link>
</head>
<body onload="setup()">
<?=$student_data?>
<h1>Checkoffs for <?=$slug?></h1>
<table id="allCheckoffs">
<thead>
<tr class="header"><th scope='col'>computing ID</th><th scope='col'>score (out of 1)</th><th scope='col'>comments</th><th scope='col'>submit</th></tr>
</thead>
</table>
<?php
}
?>
