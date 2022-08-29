<?php

require 'vendor/autoload.php';

use Sophivorus\EasyWiki;
use Google\Cloud\Translate\V3\TranslationServiceClient;

// Config
$api = 'https://www.appropedia.org/w/api.php';
$rest = 'https://www.appropedia.org/w/rest.php';
$botUser = 'Bot@Scripts';
$botPass = '7c4gi18mctees0ehvt37spibiiks04ro';
$googleCloudCredentials = '/home/appropedia/google-cloud-credentials.json';
$googleCloudProject = 'appropedia-348518';

// Get script options
$options = getopt( 't:l:', [ 'title:', 'language:' ] );
list( $title, $language ) = array_values( $options );
$title or exit( '--title parameter required' . PHP_EOL );
$language or exit( '--language parameter required' . PHP_EOL );

// Initialize EasyWiki
$wiki = new EasyWiki( $api );

// Get wikitext
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
$authors = $json['Authors'];
$translatedTitle = googleTranslate( $title, $language );
// @todo The first regexes affect {{Project data}} too
$wikitext = preg_replace( "/\n\| ?authors ?= ?[^\n]*/", '', $wikitext ); // Remove previous authors
$wikitext = preg_replace( "/\n\| ?derivative-of ?= ?[^\n]*/", '', $wikitext ); // Remove previous derivative-of
$wikitext = preg_replace( "/\n\| ?language ?= ?[^\n]*/", '', $wikitext ); // Remove previous language
$wikitext = preg_replace( "/\n\| ?title ?= ?[^\n]*/", '', $wikitext ); // Remove previous display title
$wikitext = str_replace( '{{Page data', "{{Page data\n| authors = $authors", $wikitext ); // Add authors
$wikitext = str_replace( '{{Page data', "{{Page data\n| derivative-of = $title", $wikitext ); // Add derivative-of
$wikitext = str_replace( '{{Page data', "{{Page data\n| language = $language", $wikitext ); // Add language
$wikitext = str_replace( '{{Page data', "{{Page data\n| title = $translatedTitle", $wikitext ); // Add display title
//var_dump( $wikitext ); exit; // Uncomment to debug

// Convert wikitext to HTML
$params = [
	'action' => 'visualeditor',
	'paction' => 'parsefragment',
	'page' => $title,
	'wikitext' => $wikitext,
];
$html = $wiki->post( $params, 'content' );
//var_dump( $html ); exit; // Uncomment to debug

// Ugly hack to fix nasty encoding issue when template parameters include single quotes
// @todo Also fix when template parameters include double quotes
// @note Maybe fix at the wikitext stage?
$html = preg_replace_callback( '/data-mw="(.*?)">/s', function ( $matches ) {
	$content = $matches[1];
	$content = str_replace( "'", '&apos;', $content );
	$content = str_replace( '&quot;', '"', $content );
	return "data-mw='$content'>";
}, $html );
//var_dump( $html ); exit; // Uncomment to debug

// Remove unwanted HTML to reduce characters sent to Google Translate
$DOM = new DOMDocument;
$html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );
libxml_use_internal_errors( true ); // Temporarily disable error reporting because DOMDocument complains about HTML5 tags
$DOM->loadHTML( $html );
libxml_clear_errors();
$xPath = new DomXPath( $DOM );

// Remove unwanted nodes
$unwantedNodes = '//style | //link | //meta';
foreach ( $xPath->query( $unwantedNodes ) as $unwantedNode ) {
	$unwantedNode->parentNode->removeChild( $unwantedNode );
}

// Remove unwanted attributes
$unwantedAttributes = [ 'data-parsoid', 'style', 'about', 'src', 'srcset' ];
foreach ( $xPath->query( '//*' ) as $node ) {
	foreach ( $unwantedAttributes as $unwantedAttribute ) {
		$node->removeAttribute( $unwantedAttribute );
	}
}
foreach ( $xPath->query( '//h1 | //h2 | //h3 | //h4 | //h5 | //h6' ) as $node ) {
	$node->removeAttribute( 'id' );
}

// Remove unwanted children
$parentNodes = '//*[ @data-mw and not( contains( @class, "mw-ref" ) ) ]';
foreach ( $xPath->query( $parentNodes ) as $parentNode ) {
	while ( $parentNode->hasChildNodes() ) {
		$child = $parentNode->firstChild;
		if ( method_exists( $child, 'getAttribute' ) ) {
			$class = $child->getAttribute( 'class' );
			if ( strpos( $class, 'mw-reference' ) !== false ) {
				break;
			}
		}
		$parentNode->removeChild( $child );
	}
}

// Translate template parameters
$translatableParams = [ 'title', 'text', 'content' ];
$nodes = '//*[ @data-mw ]';
foreach ( $xPath->query( $nodes ) as $node ) {
	$data = $node->getAttribute( 'data-mw' );
	$data = json_decode( $data, true );
	$params = $data['parts'][0]['template']['params'];
	if ( !$params ) {
		continue;
	}
	foreach ( $params as $param => $value ) {
		if ( in_array( $param, $translatableParams ) ) {
			$params[ $param ]['wt'] = googleTranslate( $value['wt'], $language );
		}
	}
	$data['parts'][0]['template']['params'] = $params;
	$data = json_encode( $data );
	$node->setAttribute( 'data-mw', $data );
}

// Get reduced HTML
$html = $DOM->saveHTML();
$html = html_entity_decode( $html );
$html = preg_replace( '/^<!DOCTYPE[^>]+>/', '', $html );
$html = preg_replace( '/<\/?html>/', '', $html );
$html = preg_replace( '/<\/?body>/', '', $html );
$html = preg_replace( '/<span><\/span>/', '', $html );
$html = trim( $html );
//var_dump( $html ); exit; // Uncomment to debug

// Get main translation
$html = googleTranslate( $html, $language );
//var_dump( $html ); exit; // Uncomment to debug

// Save translated HTML
$params = [
	'formatversion' => 2,
	'action' => 'visualeditoredit',
	'paction' => 'save',
	'page' => "$title/$language",
	'html' => $html,
	'token' => $wiki->getToken()
];
$wiki->login( $botUser, $botPass );
$data = $wiki->post( $params );
//var_dump( $data ); exit;

function googleTranslate( $html, $language ) {
	global $googleCloudCredentials, $googleCloudProject;

	// Check limits
	$length = strlen( $html );
	if ( $length > 30000 ) {
		exit( 'Page too long' . PHP_EOL );
	}
	$chars = file_get_contents( 'translate' );
	$chars += $length;
	if ( $chars > 490000 ) {
		exit( 'Monthly quota reached' . PHP_EOL );
	}
	file_put_contents( 'translate', $chars );

	// Translate HTML
	$GoogleTranslateClient = new TranslationServiceClient( [ 'credentials' => $googleCloudCredentials ] );
	$GoogleFormattedParent = $GoogleTranslateClient->locationName( $googleCloudProject, 'global' );
	$response = $GoogleTranslateClient->translateText( [ $html ], $language, $GoogleFormattedParent );
	$GoogleTranslateClient->close();
	foreach ( $response->getTranslations() as $translation ) {
		return $translation->getTranslatedText();
	}
	
}