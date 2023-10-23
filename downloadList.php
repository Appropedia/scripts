<?php

/**
 * This script exports a simple wikitext list as a standalone txt file
 */

if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}

$title = $_GET['title'];
$title = preg_replace( '/\s+/', '_', $title );

$extract = file_get_contents( "https://www.appropedia.org/w/api.php?action=parse&page=$title&prop=sections&format=json" );
$extract = json_decode( $extract, true );

/*
$sectionName = $_GET['section'];
if ( !array_key_exists( 'section', $_GET ) ) {
	exit( 'Section required' );
}
*/

$sectionName = $_GET['section-selector'] ?? 'Bill of materials';

foreach ( $extract['parse']['sections'] as $k => $section ) {
	if ( strtolower( $section['anchor'] ) == preg_replace( '/(\s+)/', '_', strtolower( $sectionName ) ) ) {
		$sectionNumber = $k + 1;
	}
}

$extract2 = file_get_contents( "https://www.appropedia.org/w/api.php?action=parse&page=$title&section=$sectionNumber&prop=wikitext&format=json" );
$extract2 = json_decode( $extract2, true );
$wikiText = json_encode( $extract2['parse']['wikitext']['*'] );
$wikiText = preg_replace( '/==.*==/', '', $wikiText );
$wikiText = trim( substr( $wikiText, 1, -1 ) );

////

$extract3 = file_get_contents( "https://www.appropedia.org/w/api.php?action=parse&page=$title&section=$sectionNumber&format=json" );
$extract3 = json_decode( $extract3, true );
$extract3 = $extract3['parse']['text']['*'];

$wikiText2 = json_encode( $extract3 );
$wikiText2 = preg_replace( '#<span class=\\\"mw-editsection\\\">(.*?)<\\\/span><\\\/h2>#', '</h2>', $wikiText2 );
$wikiText2 = preg_replace( '#(?=<!--)([\s\S]*?)-->#', '', $wikiText2 );

////

if ( $_SERVER['REQUEST_METHOD'] === 'GET' and isset( $_GET['downloadFile'] ) ) {
	downloadFile( strip_tags( $wikiText2 ) );
}

function downloadFile( $wikiText2 ) {
	header( 'Content-Type: text/plain' );
	header( 'Content-Disposition: attachment; filename=section.txt' );
	echo strip_tags( $wikiText2 );
	exit;
}

?>

<!DOCTYPE HTML>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>OSHWA</title>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
		<style>
			.container {
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
	
		<h1>Export a section as text</h1>

		<p>Use this page to download a text version of a page, for example, bills of materials and tool lists (if available). You are downloading the following section: <strong><?php echo $sectionName; ?></strong></p>

		<h2>Download</h2>

		<form class="my-3" action="downloadList.php" method="get">
			<input type="text" name="title" value="<?php echo $title; ?>" type="hidden" class="visually-hidden" readonly />
			<input type="submit" name="downloadFile" value="Download file" />
		</form>

		<div class="my-5">
			<h2>Preview</h2>
			<pre id="textonly" class="px-5 border border-primary text-bg-light" style="--bs-border-opacity: .5;"><code>
				<?php echo json_decode( $wikiText2 ) ?? '<h2>Select a valid section</h2>'; ?>
			</code></pre>
		</div>

		<h2>Choose a section</h2>

		<form name="section-selector" method="get">
			<input type="text" name="title" aria-label="Section selector" class="visually-hidden" value="<?php echo $title; ?>">
			<select class="form-select form-select-lg" type="submit" name="section-selector" onchange="document.getElementsByName('section-selector')[0].submit()">
				<option selected><?php echo $sectionName ?? 'Select an option'; ?></option>
				<hr>
				<option value="Tool list">Tool list</option>
				<option value="Making instructions">Making instructions</option>
				<option value="Assembly instructions">Assembly instructions</option>
				<option value="Operating instructions">Operating instructions</option>
				<option value="Disposal instructions">Disposal instructions</option>
				<option value="Bill of materials">Bill of materials</option>
			</select>
		</form>

	</body>
</html>