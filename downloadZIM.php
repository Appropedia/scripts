<?php

/**
 * This script generates and downloads a ZIM file containing a specified set of pages
 * It uses the mwoffliner library to generate the ZIM file, see https://github.com/openzim/mwoffliner
 * and EasyWiki to interact with the Appropedia API, see https://github.com/Sophivorus/EasyWiki
 */

// Debug mode
$debug = $_GET['debug'] ?? false;
if ( $debug ) {
	error_reporting( E_ALL );
	ini_set( 'display_errors', 1 );
}

$title = $_GET['title'] ?? 'appropedia';
$titlee = str_replace( ' ', '_', $title );
$date = date( 'Y-m' );
$filename = $titlee . '_' . $date . '.zim';
$filepath = 'zims/' . $filename;

if ( !file_exists( $filepath ) ) {

	// Load EasyWiki
	require 'vendor/autoload.php';

	// Get the pages
	if ( !array_key_exists( 'pages', $_GET ) ) {
		exit;
	}
	$pages = $_GET['pages'];

	// Query the API
	$pages = urldecode( $pages );
	$pages = str_replace( '_', ' ', $pages );
	$pages = str_replace( ',', '|', $pages );
	$params = [ 'titles' => $pages ];
	$api = new EasyWiki( 'https://www.appropedia.org/w/api.php' );
	$result = $api->query( $params );
	//echo '<pre>'; var_dump( $result ); exit; // Uncomment to debug

	// Replace spaces for understcores or mwoffliner won't work
	$titles = $api->find( 'title', $result );
	if ( is_string( $titles ) ) {
		$titles = [ $titles ];
	}
	$titles = array_map( function ( $title ) {
		return str_replace( ' ', '_', $title );
	}, $titles );
	//echo '<pre>'; var_dump( $titles ); exit; // Uncomment to debug

	// Build the mwoffliner command
	$icon = $_GET['icon'] ? urldecode( $_GET['icon'] ) : 'https://www.appropedia.org/logos/Appropedia-kiwix.png';
	$command = 'mwoffliner';
	$command .= ' --adminEmail=admin@appropedia.org';
	$command .= ' --customZimDescription="Shared knowledge to build rich, sustainable lives"';
	$command .= ' --customZimFavicon=' . $icon;
	$command .= ' --customZimTitle="' . $title . '"';
	$command .= ' --filenamePrefix=' . $titlee;
	$command .= ' --mwUrl=https://www.appropedia.org/';
	$command .= ' --outputDirectory=/home/appropedia/public_html/scripts/zims';
	$command .= ' --osTmpDir=/home/appropedia/public_html/scripts/zims';
	$command .= ' --publisher=Appropedia';
	$command .= ' --requestTimeout=120';
	$command .= ' --webp';
	$command .= ' --articleList="' . implode( ',', $titles ) . '"';
	$command .= ' --forceRender=RestApi';
	$command .= ' --verbose';
	//echo '<pre>' . $command; exit; // Uncomment to debug
	exec( $command, $output );
	//echo '<pre>' . var_dump( $output ); exit; // Uncomment to debug
}

// Download the ZIM file
header( 'Content-Type: application/octet-stream' );
header( 'Content-Disposition: attachment; filename=' . $filename );
readfile( $filepath );
