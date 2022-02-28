<?php

if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}
$titlee = $_GET['title']; // Extra "e" means "encoded"
$title = str_replace( '_', ' ', $titlee );

// Timestamp
$content = file_get_contents("https://www.appropedia.org/w/api.php?action=query&prop=revisions&titles=$titlee&rvslots=*&rvprop=content|timestamp&formatversion=latest&format=json");
$json_values = json_decode($content, true);
$pageid = $json_values["query"]["pages"][0]["pageid"];
if ( !isset( $pageid ) ) {
	exit( 'Request must exist' );
}
$timestamp = substr($json_values["query"]["pages"][0]["revisions"][0]["timestamp"], 0, -10);

// Semantic properties
$properties = file_get_contents( "https://www.appropedia.org/w/rest.php/semantic/v0/$titlee" );
$properties = json_decode( $properties, true );
$keywords       = $properties['Keywords'] ?? '';
$pageAuthors    = $properties['Page authors'] ?? '';
$projectAuthors = $properties['Project authors'] ?? '';
$status         = $properties['Status'] ?? '';
$made           = $properties['Made'] ?? '';
$uses           = $properties['Uses'] ?? '';
$instanceOf     = $properties['Instance of'] ?? '';
$license        = $properties['License'] ?? 'CC-BY-SA-4.0';
$sdg            = $properties['SDG'] ?? '';

// Extract
$extract = file_get_contents("https://www.appropedia.org/w/api.php?action=query&prop=extracts&exsentences=5&exlimit=1&titles=$titlee&formatversion=latest&explaintext=1&format=json");
$extract = json_decode($extract, true);
$extract = trim(str_replace("\n", " ", $extract["query"]["pages"][0]["extract"]), 200);

// Metadata
$metadata = file_get_contents("https://www.appropedia.org/w/api.php?action=query&title=$titlee&action=expandtemplates&text={{FIRSTREVISIONUSER}}--{{FULLPAGENAME}}--{{PAGELANGUAGE}}--{{FIRSTREVISIONTIMESTAMP}}--{{REALNAME:{{FIRSTREVISIONUSER}}}}&format=json");
$metadata = json_decode($metadata, true);
$metadata = $metadata["expandtemplates"]["*"];
$metadata = explode("--", $metadata);
$userCreated = "User:" . $metadata[0];
$dateCreated = preg_replace("/^(\d{4})(\d{2})(\d{2})$/", "$1-$2-$3",  substr($metadata[3], 0, 8));
$nameCreated = $metadata[4] != "Anonymous1" ? $metadata[4] : $userCreated;
$pageLanguage = $metadata[2] ?? 'en';

// Images
$image = file_get_contents("https://www.appropedia.org/w/api.php?action=query&titles=$titlee&formatversion=latest&prop=pageimages&format=json&pithumbsize=1000");
$image = json_decode($image, true);
$image = $image["query"]["pages"][0]["thumbnail"]["source"];

// Revisions
$version = file_get_contents("https://www.appropedia.org/w/api.php?action=query&titles=$titlee&prop=revisions&rvprop=ids|userid&rvlimit=max&format=json");
$version = json_decode( $version, true );
$version = count( array_shift( $version["query"]["pages"] )["revisions"] );

// Feed a comma-separated list of authors.
// function getAuthorNames($authorList) {
  // $authorsArray = array_map('trim', explode(",", $authorList));
  // $authorsArray = array_map('trim', explode(",", $authors));
  
  // $authorsUsers = preg_grep("/^User:/", $authorsArray);
  // $authorsNotUsers = preg_grep("/^User:/", $authorsArray, PREG_GREP_INVERT);
  // echo json_encode($authors);

  // $usersNames = "{{Databox/name|" . implode("}}--{{Databox/name|", $authorsUsers) . "}}";
  // $jsonPublishers = file_get_contents("https://www.appropedia.org/w/api.php?action=query&action=expandtemplates&text=$usersNames&prop=wikitext&format=json");
  // $usersNames = json_decode($jsonPublishers, true);
  // $usersNames = $usersNames["expandtemplates"]["wikitext"];
  // $usersNames = explode("--", $usersNames);
  // $publisherNames = array();
  // foreach ($usersNames as $k=>$p){
    // if (substr($usersNames[0], 0, 2) == "[["){
      // $p = trim(trim($p, "[]"), "[]");
      // preg_match("/(?<=\|).*/", $p, $match);
      // $publisherNames[$k][0] = $authorsUsers[$k];
      // $publisherNames[$k][1] = $match[0];
    // } else {
      // $publisherNames[$k][0] = $p;
    // }
  // }
  
  // if ( isset( $publisherNames[0] ) && $publisherNames[0] != '' ){
    // $contactText = 
        // "  name: " 
      // . $publisherNames[0][1] . "\n"
      // . "  social:\n"
      // . "    - platform: Appropedia\n"
      // . "      user-handle: " . $publisherNames[0][0] . "\n";
  // }
  
  // $contributorsUsers = array_shift($publisherNames);
  
  // if ( isset(array_shift($contributorsUsers)) | isset($authorsNotUsers) ){
    
  // }
  // return $authorsNotUsers;
// }

// Build the YAML file
header( "Content-Type: application/x-yaml" );
header( "Content-Disposition: attachment;filename=$titlee.yaml" );

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
description: $extract" ) : '' ) . ( $uses ? ( "
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
  name: . $nameCreated" . ( $affiliations ? "
  affiliation: $affiliations" : '' ) . "
  contact: https://www.appropedia.org/" . str_replace( ' ', '_', $userCreated ) . "
  documentation-home: https://www.appropedia.org/$titlee

# User-defined fields" . ( $sdg ? "
sustainable-development-goals: $sdg" : '' );