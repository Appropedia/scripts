<?php

/**
 * This script exports a simple wikitext list as a standalone txt file
 */

if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}

$title = $_GET['title'];
$title = preg_replace("/\s+/", "_", $title);

$extract = file_get_contents("https://www.appropedia.org/w/api.php?action=parse&page=$title&prop=sections&format=json");
$extract = json_decode($extract, true);

$sectionName = $_GET['section'];
if ( !array_key_exists( 'section', $_GET ) ) {
	
}
foreach ($extract["parse"]["sections"] as $k=>$section){
	if (strtolower($section["anchor"]) == strtolower($sectionName)){
		$sectionNumber = $k + 1;
	}
}

$extract2 = file_get_contents("https://www.appropedia.org/w/api.php?action=parse&page=$title&section=$sectionNumber&prop=wikitext&format=json");
$extract2 = json_decode($extract2, true);

$wikiText = json_encode($extract2["parse"]["wikitext"]["*"]);
$wikiText = preg_replace("/==.*==/", "", $wikiText);
$wikiText = trim(substr($wikiText, 1, -1));

////
$extract3 = file_get_contents("https://www.appropedia.org/w/api.php?action=parse&page=$title&section=$sectionNumber&format=json");
$extract3 = json_decode($extract3, true)["parse"]["text"]["*"];

$wikiText2 = json_encode($extract3);
$wikiText2 = preg_replace('#<span class=\\\"mw-editsection\\\">(.*?)<\\\/span><\\\/h2>#', '</h2>', $wikiText2);
$wikiText2 = preg_replace('#(?=<!--)([\s\S]*?)-->#', '', $wikiText2);
////

?>

<!DOCTYPE html>
<html>
	<head>
		<!--Import Google Icon Font-->
		<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
		<!--Import materialize.css-->
		<link type="text/css" rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css"	media="screen,projection"/>
		<!--Let browser know website is optimized for mobile-->
		<meta name="viewport" content="width=device-width, initial-scale=1.0"/>

		<style>
			h1{
				font-size: 1.4em;
			}
			h2{
				font-size: 1.2em;
			}
			ul:not(.browser-default) > li{
				list-style-type: disc;
			}
		</style>
	</head>

	<body class="container section">
	
	<h1>Export content</h1>
	
	<div id="textonly" class="container section grey lighten-5" style="padding:2em 3em;"> 
		<?php echo json_decode($wikiText2); ?>
	</div>
	<br>
	<div class="container">
	<button class="btn waves-light blue lighten-3" data-clipboard-target="#textonly">Copy as text</button>
	<button class="btn waves-light blue lighten-3" data-clipboard-text="<?php echo htmlspecialchars(json_decode($wikiText2)); ?>">Copy as HTML</button>
	</div>
	
		<!--JavaScript at end of body for optimized loading-->
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
		<script src="//cdnjs.cloudflare.com/ajax/libs/list.js/2.3.1/list.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.10/clipboard.min.js"></script>

		<script>
			var clipboard = new ClipboardJS('.btn');

			clipboard.on('success', function (e) {
				console.info('Action:', e.action);
				console.info('Text:', e.text);
				console.info('Trigger:', e.trigger);
			});

			clipboard.on('error', function (e) {
				console.info('Action:', e.action);
				console.info('Text:', e.text);
				console.info('Trigger:', e.trigger);
			});
		</script>
	</body>
</html>