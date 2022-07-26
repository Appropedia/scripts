<?php

/**
 * This script looks at pages to extract 
 * subpages and will return an Appropedia-style 
 * menu in the form of wikitext.
 */

if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}
$title = $_GET['title'];
$title = preg_replace("/\s+/", "_", $title);

// Request subpages to the API.
$metadata = file_get_contents( "https://www.appropedia.org/w/api.php?action=query&list=allpages&aplimit=100&apprefix=$title/&format=json" );
$metadata = json_decode($metadata, true);
$metadata = $metadata["query"]["allpages"];

// Using the number of forward slashes as 
// a parameter for the page's depth.
$pages = [];
foreach ($metadata as $m){
	$pages[$m["title"]] = substr_count($m["title"], '/');
}

// Create the menu string for each page. 
// It will generate asterisks according to 
// the depth level.
$text_array = [];
foreach ($pages as $k => $p){
  array_push($text_array, 
    str_repeat("*", $p) 
    . " [[" 
    . $k 
    . "|" 
    . basename($k) 
    . "]]<br>"
  );
}
$text = implode("", $text_array);

// Resulting text
echo(
"<code>{{Menu<br>
| class = sequence<br>
| title = [[" . preg_replace("/_/", " ", $title) . "]]<br>
| content =<br><br>"
. $text . "}}</code>"
);