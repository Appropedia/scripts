<?php

/**
 * This script returns a list of pages for generating a ZIM file for use at Kiwix
 */

// List of private categories due to Extension:CategoryLockdown or others
// see $wgCategoryLockdown in LocalSettings.php to keep the list updated
$privateCategories = [
	'Category:Medical Makers Private'
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
//echo '<pre>'; var_dump( $titles ); exit; // Uncomment to debug

// Get all the private pages
$params2 = [
	'action' => 'askargs',
	'conditions' => implode( '|', $privateCategories ),
];
$result2 = $api->get( $params2 );
//echo '<pre>'; var_dump( $result2 ); exit; // Uncomment to debug
$results2 = $api->find( 'results', $result2 );
$titles2 = array_keys( $results2 );

// Filter the private pages
$titles = array_diff( $titles, $titles2 );
//echo '<pre>'; var_dump( $titles ); exit; // Uncomment to debug

// Print the titles, encoded
header( 'Content-Type: text/csv; charset=utf-8' );
foreach ( $titles as $title ) {
	$title = str_replace( ' ', '_', $title );
	$title = '"' . $title . '"';
	echo $title . PHP_EOL;
}
