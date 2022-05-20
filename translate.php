<?php

// Set paths
$api = 'https://www.appropedia.org/w/api.php';
$rest = 'https://www.appropedia.org/w/rest.php';
$EasyWiki = '/home/appropedia/EasyWiki/EasyWiki.php';
$GoogleCloudSDK = '/home/appropedia/google-cloud-sdk/bin/gcloud';

// Extract the script options
$options = getopt( 't:l:u:p:', [ 'title:', 'language:', 'user:', 'pass:' ] );
list( $title, $language, $user, $pass ) = array_values( $options );

// Initialize EasyWiki
require_once $EasyWiki;
$wiki = new EasyWiki( $api, $user, $pass );

// Get the wikitext
$wikitext = "{{Automatic translation}}\n";
$wikitext .= $wiki->getWikitext( $title );
//var_dump( $wikitext ); exit; // Uncomment to debug

// Fix self-links
$wikitext = str_replace( "[[$title|", "[[$title/$language|", $wikitext );
$wikitext = str_replace( "[[$title#", "[[$title/$language#", $wikitext );
$wikitext = str_replace( "[[$title]]", "[[$title/$language]]", $wikitext );
//var_dump( $wikitext ); exit; // Uncomment to debug

// Adjust page data
$data = file_get_contents( $rest . '/semantic/v0/' . str_replace( ' ', '_', $title ) );
$json = json_decode( $data, true );
$authors = $json['Page authors'];
$translatedTitle = googleTranslate( $title, $language );
$wikitext = preg_replace( "/\n\| ?authors ?= ?[^\n]*/", '', $wikitext ); // Remove previous authors
$wikitext = preg_replace( "/\n\| ?derivative-of ?= ?[^\n]*/", '', $wikitext ); // Remove previous derivative-of
$wikitext = preg_replace( "/\n\| ?language ?= ?[^\n]*/", '', $wikitext ); // Remove previous language
$wikitext = preg_replace( "/\n\| ?title ?= ?[^\n]*/", '', $wikitext ); // Remove previous display title
$wikitext = str_replace( '{{Page data', "{{Page data\n| authors = $authors", $wikitext ); // Add authors
$wikitext = str_replace( '{{Page data', "{{Page data\n| derivative-of = $title", $wikitext ); // Add derivative-of
$wikitext = str_replace( '{{Page data', "{{Page data\n| language = $language", $wikitext ); // Add language
$wikitext = str_replace( '{{Page data', "{{Page data\n| title = $translatedTitle", $wikitext ); // Add display title
//var_dump( $wikitext ); exit; // Uncomment to debug

// Convert the wikitext to HTML
$params = [
	'action' => 'visualeditor',
	'paction' => 'parsefragment',
	'page' => $title,
	'wikitext' => $wikitext,
];
$html = $wiki->post( $params, 'content' );
//var_dump( $html ); exit; // Uncomment to debug

// Remove unwanted HTML to reduce characters sent to Google Translate
$DOM = new DOMDocument;
libxml_use_internal_errors( true ); // Temporarily disable error reporting because DOMDocument complains about HTML5 tags
$DOM->loadHTML( $html );
libxml_clear_errors();
$xPath = new DomXPath( $DOM );

// Remove unwanted nodes
$unwantedNodes = '//style';
foreach ( $xPath->query( $unwantedNodes ) as $unwantedNode ) {
	$unwantedNode->parentNode->removeChild( $unwantedNode );
}

// Remove unwanted attributes
$unwantedAttributes = [ 'data-parsoid' ];
foreach ( $xPath->query( '//*' ) as $node ) {
	foreach ( $unwantedAttributes as $unwantedAttribute ) {
		$node->removeAttribute( $unwantedAttribute );
	}
}

// Remove unwanted children
$parentNodes = '//*[ @data-mw and not( contains( @class, "mw-ref" ) ) ]';
foreach ( $xPath->query( $parentNodes ) as $parentNode ) {
	while ( $parentNode->hasChildNodes() ) {
		$parentNode->removeChild( $parentNode->firstChild );
	}
}

// Get the reduced HTML
$html = $DOM->saveHTML();
//var_dump( $html ); exit; // Uncomment to debug

// Ugly hack to fix encoding issue when template parameters have single quotes
// @todo Also fix encoding issue when template parameters have double quotes
// @note Maybe fix at the wikitext stage
$html = preg_replace_callback( '/data-mw="(.*?)"/s', function ( $matches ) {
	$content = $matches[1];
	$content = str_replace( "'", '&apos;', $content );
	$content = str_replace( '&quot;', '"', $content );
	return "data-mw='$content'";
}, $html );
//var_dump( $html ); exit; // Uncomment to debug

// Translate the HTML
$translation = googleTranslate( $html, $language );
//var_dump( $translation ); exit; // Uncomment to debug

// Save the translated HTML
$params = [
	'formatversion' => 2,
	'action' => 'visualeditoredit',
	'paction' => 'save',
	'page' => "$title/$language",
	'html' => $translation,
	'token' => $wiki->getToken()
];
$data = $wiki->post( $params );
//var_dump( $data ); exit;

/**
 * Translate HTML using Google Translate
 */
function googleTranslate( $html, $targetLanguageCode ) {
	global $GoogleCloudSDK;

	// Get the authorization token
	$token = exec( "$GoogleCloudSDK auth print-access-token" );

	// Build the request
	$payload = json_encode( [
		'target_language_code' => $targetLanguageCode,
		'contents' => $html
	] );

	$headers = [
		"Authorization: Bearer $token",
		'Content-Type: application/json'
	];

	// Do the request
	$curl = curl_init( 'https://translation.googleapis.com/v3beta1/projects/wikimix/locations/global:translateText' );
	curl_setopt( $curl, CURLOPT_POST, true );
	curl_setopt( $curl, CURLOPT_POSTFIELDS, $payload );
	curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
	curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
	$json = curl_exec( $curl );
	curl_close( $curl );

	// Process the output and return it
	$data = json_decode( $json );
	if ( ! property_exists( $data, 'translations' ) ) {
		var_dump( $data );
		exit;
	}

	// Unwrap and return the translation
	$translation = $data->translations;
	$translation = array_shift( $translation );
	$translation = $translation->translatedText;
	$translation = html_entity_decode( $translation, ENT_QUOTES, 'UTF-8' );
	return $translation;
}