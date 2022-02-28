<?php
/**
 * This script generates books
 * Books are wiki pages in the Book namespace
 * they are meant to be downloaded as PDFs
 */

if ( !$_POST ) {
	exit( 'Request must be POSTed' );
}

// Extract the data
$title = $_POST['title'];
$chapters = $_POST['chapters'];

// Build the wikitext
$text = '{{Book';
$i = 0;
foreach ( $chapters as $name => $page ) {
	$i++;
	$text .= "\n| chapter$i = $name";
	$text .= "\n| chapter$i-page = $page";
}
$text .= "\n}}";

// Create the book page
require __DIR__ . '/w/includes/WebStart.php';
$Config = HashConfig::newInstance();
$User = User::newSystemUser( 'BookBot' );
$timestamp = wfTimestampNow();
$Title = Title::newFromText( $title, NS_BOOK );
$Revision = new WikiRevision( $Config );
$Revision->setTitle( $Title );
$Revision->setModel( 'wikitext' );
$Revision->setText( $text );
$Revision->setUserObj( $User );
$Revision->setTimestamp( $timestamp );
$Revision->importOldRevision(); // Deprecated method

// End request
$MediaWiki = new MediaWiki();
$MediaWiki->doPostOutputShutdown();