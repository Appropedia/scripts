<?php

/**
 * This script takes a project title
 * generates an Open Know How Manifest
 * and downloads it
 */

error_reporting( 0 );
ini_set( 'display_errors', 0 );

require 'vendor/autoload.php';

if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}
$titlee = $_GET['title']; // Extra "e" means "encoded"
$title = stripcslashes( str_replace( '_', ' ', $titlee ) ); // Basic decoding

// Connect to the API
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
$extract = $api->find( 'extract', $result );
$extract = str_replace( '"', "'", $extract );
$extract = str_replace( "\n", ' ', $extract );
$extract = trim( $extract );
$image = $api->find( 'source', $result );
$revisions = $api->find( 'revisions', $result );
$timestamp = $revisions[0]['timestamp'];
$timestamp = substr( $timestamp, 0, -10 );
$version = count( $revisions );

// Get the semantic properties
$properties = [];
$contents = @file_get_contents( 'https://www.appropedia.org/w/rest.php/v1/page/' . urlencode( $titlee ) . '/semantic' );
if ( $contents ) {
	$properties = json_decode( $contents, true );
}
$keywords = $properties['Keywords'] ?? '';
$authors = $properties['Project authors'] ?? $properties['Authors'] ?? '';
$status = $properties['Status'] ?? '';
$made = $properties['Made'] ?? '';
$uses = $properties['Uses'] ?? '';
$instanceOf = $properties['Instance of'] ?? '';
$license = $properties['License'] ?? 'CC-BY-SA-4.0';
$sdg = $properties['SDG'] ?? '';
$language = $properties['Language code'] ?? 'en';

// Build the YAML file
header( "Content-Type: application/x-yaml" );
header( "Content-Disposition: attachment; filename = $titlee.yaml" );

echo "
# Open know-how manifest 1.0
# The content of this manifest file is licensed under a Creative Commons Attribution 4.0 International License. 
# Licenses for modification and distribution of the hardware, documentation, source-code, etc are stated separately.

# Manifest metadata
date-created: $dateCreated
date-updated: $timestamp
manifest-author:
  name: OKH Bot
  affiliation: Appropedia
  email: support@appropedia.org
documentation-language: $language
 
# Properties
title: $title" . ( $extract ? ( "
description: \"$extract\"" ) : '' ) . ( $uses ? ( "
intended-use: $uses" ) : '' ) . ( $keywords ? ( '
keywords:
  - ' . str_replace( ',', "\n  -", $keywords ) ) : '' ) . "
project-link: https://www.appropedia.org/$titlee
contact:
  name: $authors
  social:
  - platform: Appropedia" . ( $authors === 'User:Anonymous1' ? '' : "
    user-handle: $authors" ) . ( $location ? "
location: $location" : '' ). ( $image ? "
image: $image" : '' ) . ( $version ? "
version: $version" : '' ) . ( $status ? "
development-stage: $status" : '' ) . ( $made ? "
made: " . ( $made === 't' ? 'true' : 'false' ) : '' ) . ( $instanceOf ? "
variant-of:
  name: $instanceOf
  web: https://www.appropedia.org/" . str_replace( ' ', '_', $instanceOf ) : '' ) . "

# License
license:
  documentation: $license
licensor:
  name: $authors" . ( $affiliations ? "
  affiliation: $affiliations" : '' ) . "
  contact: https://www.appropedia.org/" . str_replace( ' ', '_', $authors ) . "
  documentation-home: https://www.appropedia.org/$titlee

# User-defined fields" . ( $sdg ? "
sustainable-development-goals: $sdg" : '' );