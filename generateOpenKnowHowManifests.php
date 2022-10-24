<?php

/**
 * This script returns a JSON array
 * with the URLs of all the Open Know How Manifests in Appropedia
 */

error_reporting( 0 );
ini_set( 'display_errors', 0 );

require 'vendor/autoload.php';

use Sophivorus\EasyWiki;

$wiki = new EasyWiki( 'https://www.appropedia.org/w/api.php' );

$params = [
	'action' => 'askargs',
	'conditions' => 'Type::Project',
];
$result = $wiki->get( $params );
//var_dump( $result ); exit; // Uncomment to debug
$results = $wiki->find( 'results', $result );

$urls = [];
foreach ( $results as $title => $values ) {
	$title = str_replace( ' ', '_', $title ); // Basic encoding
	$url = "https://www.appropedia.org/scripts/generateOpenKnowHowManifest.php?title=$title";
	$urls[] = $url;
}

header( 'Content-Type: application/json; charset=utf-8' );
echo json_encode( $urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );