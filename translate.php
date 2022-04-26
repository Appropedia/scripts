<?php

// Set paths
$api = 'https://www.appropedia.org/w/api.php';
$EasyWiki = '/home/appropedia/EasyWiki/EasyWiki.php';
$GoogleCloudSDK = '/home/appropedia/google-cloud-sdk/bin/gcloud';

// Extract the script options
$options = getopt( 't:l:u:p:', [ 'title:', 'language:', 'user:', 'pass:' ] );
list( $title, $language, $user, $pass ) = array_values( $options );

// Initialize EasyWiki
require_once $EasyWiki;
$wiki = new EasyWiki( $api, $user, $pass );

// Build the translation notice
$wikitext = '{{Automatic translation';
$wikitext .= '| page = ' . $title;
$wikitext .= '| language = ' . $language;
$wikitext .= '}}';
//var_dump( $wikitext ); exit; // Uncomment to debug

// Add the wikitext of the page
$wikitext .= $wiki->getWikitext( $title );
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
$DOM->loadHTML( $html );
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
$parentNodes = '//*[@data-mw] and not( contains( @class, "mw-ref" ) )';
foreach ( $xPath->query( $parentNodes ) as $parentNode ) {
	while ( $parentNode->hasChildNodes() ) {
		$parentNode->removeChild( $parentNode->firstChild );
	}
}

// Get the reduced HTML
$html = $DOM->saveHTML();
//var_dump( $html ); //exit; // Uncomment to debug

// Fix encoding issue caused by template parameters with single quotes
// @todo Fix encoding issue caused by template parameters with double quotes
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