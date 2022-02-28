<?php
/**
 * This script generates PDFs using the PagedJS library
 * https://www.pagedjs.org
 */

if ( !array_key_exists( 'title', $_GET ) ) {
	exit( 'Title required' );
}
$title = $_GET['title'];

// Generate the PDF
$encodedTitle = rawurlencode( $title );
exec( "/home/appropedia/node_modules/.bin/pagedjs-cli 'https://www.appropedia.org/$encodedTitle' -t 60000 --browserArgs '--no-sandbox'" );

// Download the PDF
$fileName = trim( str_replace( '?', '', str_replace( '_', ' ', str_replace( 'Book:', '', $title ) ) ) );
header( 'Content-type:application/pdf' );
header( "Content-Disposition:attachment;filename=$fileName.pdf" );
readfile( 'output.pdf' );
unlink( 'output.pdf' );