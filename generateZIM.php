<?php

// Debug mode
$debug = $_GET['debug'] ?? false;
if ( $debug ) {
	error_reporting( E_ALL );
	ini_set( 'display_errors', 1 );
}

require 'vendor/autoload.php';

// Connect to the Appropedia API
$api = new EasyWiki( 'https://www.appropedia.org/w/api.php' );

$category = $_GET['category'] ?? null;

$pages = $_GET['pages'] ?? null;

$title = $_GET['title'] ?? $category ?? null;
$title = str_replace( '_', ' ', $title );
$titlee = str_replace( ' ', '_', $title );

$filenamePrefix = strtolower( $titlee );

// Build the query
if ( $category ) {
	$params = [
		'generator' => 'categorymembers',
		'gcmtitle' => 'Category:' . $category,
		'gcmtype' => 'page',
		'gcmlimit' => 'max',
	];
}

// Get all the titles
$result = $api->query( $params );
//echo '<pre>'; var_dump( $result ); exit; // Uncomment to debug
$titles = $api->find( 'title', $result );
while ( $continue = $api->find( 'gcmcontinue', $result ) ) {
	$params['gcmcontinue'] = $continue;
	$result = $api->query( $params );
	$moreTitles = $api->find( 'title', $result );
	$titles = array_merge( $titles, $moreTitles );
}

// Replace spaces for understcores or mwoffliner won't work
$titles = array_map( function ( $title ) {
	return str_replace( ' ', '_', $title );
}, $titles );
//echo '<pre>'; var_dump( $titles ); exit; // Uncomment to debug

$command = 'mwoffliner';
$command .= ' --adminEmail=admin@appropedia.org';
$command .= ' --customZimDescription="Shared knowledge to build rich, sustainable lives"';
$command .= ' --customZimFavicon=https://www.appropedia.org/logos/Appropedia-zim.png';
$command .= ' --customZimTitle="' . $title . '"';
$command .= ' --filenamePrefix=' . $filenamePrefix;
$command .= ' --mwUrl=https://www.appropedia.org/';
$command .= ' --outputDirectory=/home/appropedia/public_html/scripts';
$command .= ' --osTmpDir=/home/appropedia/public_html/scripts';
$command .= ' --publisher=Appropedia';
$command .= ' --requestTimeout=120';
$command .= ' --webp';
$command .= ' --articleList=' . implode( ',', $titles );
$command .= ' --forceRender=RestApi';
$command .= ' --verbose';

exec( $command, $output );
//echo '<pre>' . $command . '<br><br>'; var_dump( $output ); exit;

$date = date( 'Y-m' );
$filename = $filenamePrefix . '_' . $date . '.zim';
header( 'Content-Type: application/octet-stream' );
header( 'Content-Disposition: attachment; filename=' . $filename );
readfile( $filename );