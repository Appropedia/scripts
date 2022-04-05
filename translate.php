<?php

// Set paths
$EasyWiki = '/home/appropedia/EasyWiki/EasyWiki.php';
$MediaWikiAPI = 'https://www.appropedia.org/w/api.php';
$GoogleCloudSDK = '/home/appropedia/google-cloud-sdk/bin/gcloud';

// Extract the script options
$options = getopt( 't:l:u:p:', [ 'title:', 'language:', 'user:', 'pass:' ] );
list( $title, $language, $user, $pass ) = array_values( $options );

// Initialize EasyWiki
require_once $EasyWiki;
$wiki = new EasyWiki( $MediaWikiAPI, $user, $pass );

// Get the wikitext of the page
$wikitext = $wiki->getWikitext( $title );
//var_dump( $wikitext ); exit; // Uncomment to debug

// Convert the wikitext to HTML
$query = [
	'action' => 'visualeditor',
	'paction' => 'parsefragment',
	'page' => $title,
	'wikitext' => $wikitext,
];
$html = $wiki->get( $query, 'content' );
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
$parentNodes = '//*[@data-mw]';
foreach ( $xPath->query( $parentNodes ) as $parentNode ) {
	while ( $parentNode->hasChildNodes() ) {
		$parentNode->removeChild( $parentNode->firstChild );
	}
}

// Get the reduced HTML
$html = $DOM->saveHTML();
//var_dump( $html ); exit; // Uncomment to debug

// Translate the HTML
$translation = googleTranslate( $html, $language );
//var_dump( $translation ); exit; // Uncomment to debug

// Save the translated HTML
$query = [
	'formatversion' => 2,
	'action' => 'visualeditoredit',
	'paction' => 'save',
	'page' => "$title/$language",
	'html' => $translation,
];
$data = $wiki->post( $query );
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