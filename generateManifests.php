<?php

/**
 * This script generates an Open Know How Manifest for each project in Appropedia
 * and adds it to the manifests directory
 */

require 'vendor/autoload.php';

use Sophivorus\EasyWiki;

// Prevent web entry
if ( !isset( $GLOBALS['argv'] ) ) {
	exit( 'This script must be run from the command line' );
}

// Delete manifests
exec( 'rm -f /home/appropedia/public_html/manifests/*' );

// Get all projects
$api = new EasyWiki( 'https://www.appropedia.org/w/api.php' );
$params = [ 'action' => 'askargs', 'conditions' => 'Type::Project' ];
$result = $api->get( $params );
//var_dump( $result ); exit; // Uncomment to debug
$results = $api->find( 'results', $result );

// Make manifest for each project
foreach ( $results as $title => $values ) {
	echo $title;
	$hash = md5( $title );
	$titlee = str_replace( ' ', '_', $title ); // Basic encoding
	$url = "https://www.appropedia.org/scripts/generateOpenKnowHowManifest.php?title=$titlee";
	$manifest = file_get_contents( $url );
	$manifest = trim( $manifest );
	file_put_contents( "../manifests/$hash.yaml", $manifest );
	echo ' .. ok' . PHP_EOL;
	sleep( 1 ); // Don't overload the server
	//exit; // Uncomment to debug
}