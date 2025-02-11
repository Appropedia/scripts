<?php

/**
 * This script returns a list of pages for generating a ZIM file for use at Kiwix
 */

// List of categories to ignore
// for example private pages due to Extension:CategoryLockdown
$categoriesToIgnore = [
	'Medical Makers Private',
	'Appropedia private'
];

// Debug mode
error_reporting( 0 );
ini_set( 'display_errors', 0 );

require 'vendor/autoload.php';

// Connect to the Appropedia API
$api = new EasyWiki( 'https://www.appropedia.org/w/api.php' );

// Get all the titles in the main namespace
$params = [
	'list' => 'allpages',
	'apnamespace' => 0,
	'aplimit' => 'max',
	'apfilterredir' => 'nonredirects',
];
$result = $api->query( $params );
//echo '<pre>'; var_dump( $result ); exit; // Uncomment to debug
$titles = $api->find( 'title', $result );
while ( $continue = $api->find( 'apcontinue', $result ) ) {
	$params['apcontinue'] = $continue;
	$result = $api->query( $params );
	$titles = array_merge( $titles, $api->find( 'title', $result ) );
}
//echo '<pre>' . implode( PHP_EOL, $titles ); exit; // Uncomment to debug

// Get all the pages to ignore
$params = [
	'action' => 'ask',
	'query' => '[[Category:' . implode( '||', $categoriesToIgnore ) . ']]',
];
$result = $api->get( $params );
//echo '<pre>'; var_dump( $result ); exit; // Uncomment to debug
$results = $api->find( 'results', $result );
$titlesToIgnore = array_keys( $results );
//echo '<pre>' . implode( PHP_EOL, $titlesToIgnore ); exit; // Uncomment to debug

// Filter the pages to ignore
$titles = array_diff( $titles, $titlesToIgnore );
//echo '<pre>' . implode( PHP_EOL, $titles ); exit; // Uncomment to debug

// Print the titles, encoded
header( 'Content-Type: text/tsv; charset=utf-8' );
header( 'Content-Disposition: attachment; filename=appropedia.tsv' );
foreach ( $titles as $title ) {
	$title = str_replace( ' ', '_', $title );
	echo $title . PHP_EOL;
}
