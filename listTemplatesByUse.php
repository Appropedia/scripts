<?php

/**
 * This script lists templates by use count
 */

require_once __DIR__ . '/../w/maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;

class ListTemplatesByUse extends Maintenance {

	public static $templates = [];

	public function execute() {
		$templates = [];
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select( 'page', 'page_id', [ 'page_namespace' => 0, 'page_is_redirect' => 0 ] );
		foreach ( $result as $row ) {
			$id = $row->page_id;
			$Title = Title::newFromID( $id );
			if ( ! $Title->exists() ) {
				continue;
			}
			$Page = WikiPage::factory( $Title );
			$Revision = $Page->getRevision();
			$Content = $Revision->getContent( Revision::RAW );
			$text = ContentHandler::getContentText( $Content );

			preg_replace_callback( '/{{([^#}|]+)/', function ( $matches ) {
				$template = $matches[1];
				$template = ucfirst( trim( $template ) );
				$count = self::$templates[ $template ] ?? 0;
				$count++;
				self::$templates[ $template ] = $count;
			}, $text );

			//break; // Uncomment to debug
		}
		$templates = self::$templates;
		arsort( $templates );
		$templates = array_splice( $templates, 0, 100 );
		var_dump( $templates );
	}
}

$maintClass = ListTemplatesByUse::class;
require_once RUN_MAINTENANCE_IF_MAIN;