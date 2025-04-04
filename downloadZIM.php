<?php

/**
 * This script generates a ZIM file for a specified set of pages and prompts the user to download it
 * It uses the mwoffliner library to generate the ZIM file, see https://github.com/openzim/mwoffliner
 * and EasyWiki to interact with the Appropedia API, see https://github.com/Sophivorus/EasyWiki
 */

// Debug mode
$debug = $_GET['debug'] ?? false;
if ( $debug ) {
	error_reporting( E_ALL );
	ini_set( 'display_errors', 1 );
}

// Load EasyWiki
require 'vendor/autoload.php';

// Check if the requested ZIM already exists
// @todo Improve caching system
$title = $_GET['title'] ?? 'appropedia';
$titlee = str_replace( ' ', '_', $title ); // Extra "e" is for "encoded"
$date = date( 'Y-m' );
$filename = $titlee . '_' . $date . '.zim';
$filepath = 'zims/' . $filename;
//echo $filepath; exit; // Uncomment to debug

if ( !file_exists( $filepath ) ) {

	// Fetch the pages
	$pages = $_GET['pages'] ?? exit( 'Error! The "pages" param is required.' );
	$pages = urldecode( $pages );
	$pages = str_replace( '_', ' ', $pages );
	$pages = str_replace( ',', '|', $pages );
	$params = [ 'titles' => $pages ];
	$api = new EasyWiki( 'https://www.appropedia.org/w/api.php' );
	$result = $api->query( $params );
	//echo '<pre>'; var_dump( $result ); exit; // Uncomment to debug

	// Replace spaces for underscores or mwoffliner won't work
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
	$description = $_GET['description'] ?? 'Powered by Appropedia, the sustainability wiki';
	$mainpage = $_GET['mainpage'] ?? '';
	$mainpagee = str_replace( ' ', '_', $mainpage );
	$command = 'mwoffliner';
	$command .= ' --adminEmail=admin@appropedia.org';
	$command .= ' --customZimTitle="' . $title . '"';
	$command .= ' --customZimFavicon=' . $icon;
	$command .= ' --customZimDescription="' . $description . '"';
	$command .= ' --customMainPage=' . $mainpagee;
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

	// Make the ZIM file (this may take several seconds)
	exec( $command, $output );
	//echo '<pre>' . var_dump( $output ); exit; // Uncomment to debug
}

// Download the ZIM file
header( 'Content-Type: application/octet-stream' );
header( 'Content-Disposition: attachment; filename=' . $filename );
readfile( $filepath );
