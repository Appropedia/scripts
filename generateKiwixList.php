<?php

/**
 * This script returns a list of pages for generating a ZIM file for use at Kiwix
 */

error_reporting( 0 );
ini_set( 'display_errors', 0 );

require 'vendor/autoload.php';

use Sophivorus\EasyWiki;

$wiki = new EasyWiki( 'https://www.appropedia.org/w/api.php' );

// Get all pages we're interested in
$params = [
	'action' => 'askargs',
	'conditions' => 'Category:Medical Makers',
];
$result = $wiki->get( $params );
//var_dump( $result ); exit; // Uncomment to debug
$results = $wiki->find( 'results', $result );
$titles = array_keys( $results );

// Get all private pages
$params2 = [
	'action' => 'askargs',
	'conditions' => 'Category:Medical Makers Private',
];
$result2 = $wiki->get( $params2 );
//var_dump( $result2 ); exit; // Uncomment to debug
$results2 = $wiki->find( 'results', $result2 );
$titles2 = array_keys( $results2 );

// Filter private pages
$titles = array_diff( $titles, $titles2 );
//var_dump( $titles ); exit; // Uncomment to debug

// Print the titles, encoded
header( 'Content-Type: text/csv; charset=utf-8' );
foreach ( $titles as $title ) {
	$title = str_replace( ' ', '_', $title );
	echo $title . PHP_EOL;
}
