<?php

header( 'Content-Type: application/json' );

$dir = '/home/appropedia/public_html/manifests/';

$list = [];

if ( is_dir( $dir ) ) {
	if ( $dir_handle = opendir( $dir ) ) {
		while ( ( $file = readdir( $dir_handle ) ) !== false ) {
			if ( substr( $file, 0, 1 ) === '.' ) {
				continue; // Skip hidden files
			}
      $url = 'https://www.appropedia.org/manifests/' . $file;
			$list[] = $url;
		}
	}
	echo json_encode( $list, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
}