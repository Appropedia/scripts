<?php

// Debug mode
error_reporting( E_ALL );
ini_set( 'display_errors', 1 );

if ( !array_key_exists( 'pages', $_GET ) ) {
	exit;
}
$pages = $_GET['pages'];
$pages = explode( '|', $pages );

$command = 'wkhtmltopdf';
$command .= ' --user-style-sheet generatePDF.css';

foreach ( $pages as $page ) {
    $url = "https://www.appropedia.org/w/rest.php/v1/page/$page/html";
    $command .= " $url";
}

$command .= ' temp.pdf';

exec( $command );

header( 'Content-Type: application/pdf' );
header( 'Content-Disposition: attachment; filename=appropedia.pdf' );
readfile( 'temp.pdf' );