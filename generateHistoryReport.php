<?php

/**
 * This script looks at a page's history 
 * to aggregate the contributions of 
 * individual users.
 */
 
if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}
?>

<!DOCTYPE html>
<html>
  <head>
    <!--Import Google Icon Font-->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!--Import materialize.css-->
    <link type="text/css" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"  media="screen,projection"/>
    <!--Let browser know website is optimized for mobile-->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

    <style>
      h1{
        font-size: 1.4em;
      }
    </style>
  </head>

  <body class="container">

<?php
$title = $_GET['title'];
$title = preg_replace("/\s+/", "_", $title);

// Request history to the API.
$history = file_get_contents( "https://www.appropedia.org/w/api.php?action=query&prop=revisions&titles=$title&rvslots=*&rvprop=timestamp|user|size&rvlimit=500&format=json" );
$history = json_decode($history, true);
$history = array_shift(array_values($history["query"]["pages"]))["revisions"];

// Edit sizes (bytes). Last line finds the last one since it doesn't need to be calculated.
$sizes = [];
for($i=0; $i < count($history)-1; $i++){
	$sizes[$i] = abs($history[$i]["size"]-$history[$i+1]["size"]);
}
array_push($sizes, end($history)["size"]);

foreach ($history as $key=>$h){
  $history[$key]["size"] = $sizes[$key];
}

$users = [];
foreach ($history as $h) {$users[] = $h['user'];}
$users = array_values(array_unique($users, SORT_STRING));

$final = [];
foreach ($users as $u){
  $userSizes = [];
  foreach ($history as $h){
    if($h["user"] == $u){
      $userSizes[] = $h["size"];
    }
  }
  $final[$u] = array_sum($userSizes);
}

asort($final);

function html_table($data = array())
{
    $rows = [];
    foreach ($data as $key=>$row) {
        $rows[] = "<tr><td><a href =\"https://www.appropedia.org/User:" . $key . "\">". $key ."</a></td><td>" . $row . "</td></tr>";
    }
    return "<table class='hci-table highlight list'><tr><th>User</th><th>Total bytes (abs)</th></tr>" . implode('', $rows) . "</table>";
}

echo "<h1>User contributions for <b>" . $title . "</b></h1>";?>

<a href="#methodology">How is this calculated?</a>

<?php echo html_table($final);

?>

<p id="methodology">This report is calculated by adding the absolute values of all user contributions to a page and aggregating them.<p>

    <!--JavaScript at end of body for optimized loading-->
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/list.js/2.3.1/list.min.js"></script>

  </body>
</html>