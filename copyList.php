<?php

/**
 * This script exports a simple wikitext list as a standalone txt file
 */

$title = $_GET['title'] ?? exit( 'Title required' );
$title = preg_replace( '/\s+/', '_', $title );

$params = [
	'action' => 'parse',
	'page' => $title,
	'prop' => 'sections',
	'format' => 'json',
];
$query = http_build_query( $params );
$extract = file_get_contents( 'https://www.appropedia.org/w/api.php?' . $query );
$extract = json_decode( $extract, true );

// Figure out the section number
$sectionName = $_GET['section-selector'] ?? 'Bill of materials';
foreach ( $extract['parse']['sections'] as $k => $section ) {
	if ( strtolower( $section['anchor'] ) === preg_replace( '/(\s+)/', '_', strtolower( $sectionName ) ) ) {
		$sectionNumber = $k + 1;
	}
}

// Get the wikitext of the selected section
$params2 = [
	'action' => 'parse',
	'page' => $title,
	'section' => $sectionNumber,
	'disableeditsection' => true,
	'disabletoc' => true,
	'format' => 'json',
];
$query2 = http_build_query( $params2 );
$extract2 = file_get_contents( 'https://www.appropedia.org/w/api.php?' . $query2 );
$extract2 = json_decode( $extract2, true );
$extract2 = $extract2['parse']['text']['*'];
$wikitext = json_encode( $extract2 );
$wikitext = preg_replace( '#(?=<!--)([\s\S]*?)-->#', '', $wikitext ); // Remove comments

?><!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>OSHWA</title>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
		<style>
			.small-middle-container {
				margin: auto;
				width: 60%;
			}
			.readonly,
			.readonly:active,
			.readonly:enabled {
				background:#eee;
			}
			h1 {
				font-size: 1.4em;
			}
			h2 {
				font-size: 1.2em;
			}
			ul:not(.browser-default) > li {
				list-style-type: disc;
			}
			pre {
				white-space: pre-wrap;
				word-wrap: break-word;
			}	
		</style>
	</head>

	<body class="container section">

	<h1>Export content</h1>

	<p>This tool will produce a downloable version of text-based page section on Appropedia. Use it to export some standardized page sections such as bills of materials and tool lists when they're available on a page.</p>

	<div class="row my-5 container">

		<div class="col-sm-6 offset-sm-3 my-5 px-5">
			<h2>Select a section</h2>
			<form name="section-selector" method="get">
				<input type="text" name="title" aria-label="Section selector" class="visually-hidden" value="<?php echo $title; ?>">
				<select class="form-select form-select-lg" type="submit" name="section-selector" onchange="document.getElementsByName('section-selector')[0].submit()">
					<option selected><?php echo $_GET['section-select'] ?? 'Select an option'; ?></option>
					<hr>
					<option value="Tool list">Tool list</option>
					<option value="Making instructions">Making instructions</option>
					<option value="Assembly instructions">Assembly instructions</option>
					<option value="Operating instructions">Operating instructions</option>
					<option value="Disposal instructions">Disposal instructions</option>
					<option value="Bill of materials">Bill of materials</option>
				</select>
			</form>
		</div>
	
	<pre id="textonly" class="col-sm-6 offset-sm-3 my-5 px-5 border border-primary text-bg-light" style="--bs-border-opacity: .5;"><code>
		<?php echo json_decode( $wikitext ) ?? '<h2>Select a valid section</h2>'; ?>
	</code></pre>

	</div>

	<div class="container">
	<button class="btn waves-light blue lighten-3" data-clipboard-target="#textonly">Copy as text</button>
	<button class="btn waves-light blue lighten-3" data-clipboard-text="<?php echo htmlspecialchars(json_decode($wikitext)); ?>">Copy as HTML</button>
	</div>

		<!--JavaScript at end of body for optimized loading-->
		<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
		<script src="//cdnjs.cloudflare.com/ajax/libs/list.js/2.3.1/list.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.10/clipboard.min.js"></script>
		<script>
			var clipboard = new ClipboardJS( '.btn' );
			clipboard.on( 'success', function ( event ) {
				console.info( 'Action:', event.action );
				console.info( 'Text:', event.text );
				console.info( 'Trigger:', event.trigger );
			} );
			clipboard.on( 'error', function ( event ) {
				console.info( 'Action:', event.action );
				console.info( 'Text:', event.text );
				console.info( 'Trigger:', event.trigger );
			} );
		</script>
	</body>
</html>