<?php

/**
 * This script takes a project title
 * and generates an Open Know How Manifest
 */

error_reporting( 0 );
ini_set( 'display_errors', 0 );

require 'vendor/autoload.php';

use Sophivorus\EasyWiki;

if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}
$titlee = $_GET['title']; // Extra "e" means "encoded"
$title = stripcslashes( str_replace( '_', ' ', $titlee ) ); // Basic decoding

// Connect to the API
$wiki = new EasyWiki( 'https://www.appropedia.org/w/api.php' );

// Timestamp
$params = [
	'titles' => $title,
	'prop' => 'revisions',
	'rvslots' => '*',
	'rvprop' => 'timestamp',
];
$result = $wiki->query( $params );
if ( !$result ) {
	exit( 'Page not found' );
}
//var_dump( $result ); exit; // Uncomment to debug
$timestamp = $wiki->find( 'timestamp', $result );
$timestamp = substr( $timestamp, 0, -10 );
if ( !$timestamp ) {
	exit( 'Page not found' );
}

// Get semantic properties
$properties = @file_get_contents( 'https://www.appropedia.org/w/rest.php/semantic/v0/' . urlencode( $titlee ) );
if ( $properties ) {
	$properties = json_decode( $properties, true );
} else {
	$properties = [];
}
$keywords       = $properties['Keywords'] ?? '';
$authors		    = $properties['Authors'] ?? '';
$projectAuthors = $properties['Project authors'] ?? '';
$status         = $properties['Status'] ?? '';
$made           = $properties['Made'] ?? '';
$uses           = $properties['Uses'] ?? '';
$instanceOf     = $properties['Instance of'] ?? '';
$license        = $properties['License'] ?? 'CC-BY-SA-4.0';
$sdg            = $properties['SDG'] ?? '';

// Get extract
$params = [
	'titles' => $title,
	'prop' => 'extracts',
	'exintro' => true,
	'exsentences' => 5,
	'exlimit' => 1,
	'explaintext' => 1,
];
$result = $wiki->query( $params );
//var_dump( $result ); exit; // Uncomment to debug
$extract = $wiki->find( 'extract', $result );
$extract = str_replace( '"', "'", $extract );
$extract = str_replace( "\n", ' ', $extract );
$extract = trim( $extract );

// Get metadata
$params = [
	'title' => $title,
	'action' => 'expandtemplates',
	'prop' => 'wikitext',
	'text' => '{{FIRSTREVISIONUSER}}--{{FULLPAGENAME}}--{{PAGELANGUAGE}}--{{FIRSTREVISIONTIMESTAMP}}--{{PAGEAUTHORS}}'
];
$result = $wiki->get( $params );
var_dump( $result ); exit; // Uncomment to debug
$metadata = $wiki->find( 'wikitext', $result );
$metadata = explode( '--', $metadata );
//var_dump( $metadata ); exit; // Uncomment to debug
$userCreated = 'User:' . $metadata[0];
$dateCreated = preg_replace( '/^(\d{4})(\d{2})(\d{2})$/', '$1-$2-$3',  substr( $metadata[3], 0, 8 ) );
$nameCreated = $metadata[4] === 'Anonymous1' ? $userCreated : $metadata[4];
$pageLanguage = $metadata[2] ?? 'en';

// Get images
$params = [
	'titles' => $title,
	'prop' => 'pageimages',
	'pithumbsize' => 1000,
];
$result = $wiki->query( $params );
//var_dump( $result ); exit; // Uncomment to debug
$image = $wiki->find( 'source', $result );

// Get revisions
$params = [
	'titles' => $title,
	'prop' => 'revisions',
	'rvprop' => 'ids|userid',
	'rvlimit' => 'max',
];
$result = $wiki->query( $params );
//var_dump( $result ); exit; // Uncomment to debug
$revisions = $wiki->find( 'revisions', $result );
$version = count( $revisions );

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
documentation-language: $pageLanguage
 
# Properties
title: $title" . ( $extract ? ( "
description: \"$extract\"" ) : '' ) . ( $uses ? ( "
intended-use: $uses" ) : '' ) . ( $keywords ? ( '
keywords:
  - ' . str_replace( ',', "\n  -", $keywords ) ) : '' ) . "
project-link: https://www.appropedia.org/$titlee
contact:
  name: $nameCreated
  social:
  - platform: Appropedia" . ( $userCreated === 'Anonymous1' ? '' : "
    user-handle: $userCreated" ) . ( $location ? "
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
  name: $nameCreated" . ( $affiliations ? "
  affiliation: $affiliations" : '' ) . "
  contact: https://www.appropedia.org/" . str_replace( ' ', '_', $userCreated ) . "
  documentation-home: https://www.appropedia.org/$titlee

# User-defined fields" . ( $sdg ? "
sustainable-development-goals: $sdg" : '' );