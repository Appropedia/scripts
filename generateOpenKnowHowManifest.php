<?php

/**
 * This script takes a project title
 * generates an Open Know How Manifest
 * and downloads it
 */

// Debug mode
$debug = $_GET['debug'] ?? false;
if ( $debug ) {
	error_reporting( E_ALL );
	ini_set( 'display_errors', 1 );
}

$title = $_GET['title'] ?? exit( 'Error! The "title" param is required.' );
$title = stripcslashes( str_replace( '_', ' ', $title ) ); // Basic decoding
$titlee = str_replace( ' ', '_', $title ); // Extra "e" means "encoded"

// Connect to the API using EasyWiki (https://github.com/Sophivorus/EasyWiki)
require 'vendor/autoload.php';
$api = new EasyWiki( 'https://www.appropedia.org/w/api.php' );

// Get the page properties
$params = [
	'titles' => $title,
	'prop' => 'extracts|pageimages|revisions',
	'exintro' => true,
	'exsentences' => 5,
	'exlimit' => 1,
	'explaintext' => 1,
	'pithumbsize' => 1000,
	'rvprop' => 'timestamp',
	'rvlimit' => '500',
];
$result = $api->query( $params );
$missing = $api->find( 'missing', $result );
if ( $missing ) {
	exit( 'Page not found' );
}
//echo '<pre>'; var_dump( $result ); exit; // Uncomment to debug

// Process the page properties
$extract = $api->find( 'extract', $result );
$extract = str_replace( "\n", ' ', $extract );
$extract = trim( $extract );
$image = $api->find( 'source', $result );
$revisions = $api->find( 'revisions', $result );
$revisions = is_string( $revisions ) ? [ $revisions ] : $revisions;
$version = count( $revisions );
$dateCreated = end( $revisions )['timestamp'];
$dateCreated = substr( $dateCreated, 0, -10 );
$dateUpdated = reset( $revisions )['timestamp'];
$dateUpdated = substr( $dateUpdated, 0, -10 );

// Get the semantic properties
$contents = @file_get_contents( 'https://www.appropedia.org/w/rest.php/v1/page/' . urlencode( $titlee ) . '/semantic' );
$properties = $contents ? json_decode( $contents, true ) : [];
$keywords = $properties['Keywords'] ?? '';
$authors = $properties['Project authors'] ?? $properties['Authors'] ?? '';
$status = $properties['Project status'] ?? '';
$made = $properties['Project was made'] ?? '';
$uses = $properties['Project uses'] ?? '';
$type = $properties['Project type'] ?? '';
$license = $properties['License'] ?? 'CC-BY-SA-4.0';
$sdg = $properties['SDG'] ?? '';
$language = $properties['Language code'] ?? 'en';
//echo '<pre>'; var_dump( $properties ); exit; // Uncomment to debug

// Process the properties
$authors = explode( ',', $authors );
$mainAuthor = $authors[0]; // @todo The next version of OKH will support multiple authors
if ( $mainAuthor == 'User:Anonymous1 ') {
	$mainAuthor = '';
}

// Build the YAML file
header( "Content-Type: application/x-yaml" );
header( "Content-Disposition: attachment; filename = $titlee.yaml" );

echo "# Open know-how manifest 1.0
# The content of this manifest file is licensed under a Creative Commons Attribution 4.0 International License. 
# Licenses for modification and distribution of the hardware, documentation, source-code, etc are stated separately.

# Manifest metadata
date-created: $dateCreated
date-updated: $dateUpdated
manifest-author:
  name: OKH Bot
  affiliation: Appropedia
  email: admin@appropedia.org
documentation-language: $language
 
# Properties
title: $title" . ( $extract ? ( "
description: $extract" ) : '' ) . ( $uses ? ( "
intended-use: $uses" ) : '' ) . ( $keywords ? ( '
keywords:
  - ' . str_replace( ',', "\n  -", $keywords ) ) : '' ) . "
project-link: https://www.appropedia.org/$titlee
contact:
  name: $mainAuthor
  social:
  - platform: Appropedia
    user-handle: $mainAuthor" . ( $location ? "
location: $location" : '' ). ( $image ? "
image: $image" : '' ) . ( $version ? "
version: $version" : '' ) . ( $status ? "
development-stage: $status" : '' ) . ( $made ? "
made: " . ( $made === 't' ? 'true' : 'false' ) : '' ) . ( $type ? "
variant-of:
  name: $type
  web: https://www.appropedia.org/" . str_replace( ' ', '_', $type ) : '' ) . "

# License
license:
  documentation: $license
licensor:
  name: $mainAuthor" . ( $affiliations ? "
  affiliation: $affiliations" : '' ) . "
  contact: https://www.appropedia.org/" . str_replace( ' ', '_', $mainAuthor ) . "
  documentation-home: https://www.appropedia.org/$titlee

# User-defined fields" . ( $sdg ? "
sustainable-development-goals: $sdg" : '' );