<?php

/**
 * This script lists the top 100 templates by use count
 */

require_once __DIR__ . '/../w/maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;

class ListTemplatesByUse extends Maintenance {

	public static $templates = [];

	public function execute() {
		$dbr = wfGetDB( DB_REPLICA );
		$result = $dbr->select( 'page', 'page_id', [ 'page_is_redirect' => 0 ] );
		foreach ( $result as $row ) {
			$id = $row->page_id;
			$Title = Title::newFromID( $id );
			if ( ! $Title->exists() ) {
				continue;
			}
			$Page = WikiPage::factory( $Title );
			if ( ! $Page ) {
				continue;
			}
			$Revision = $Page->getRevision();
			if ( ! $Revision ) {
				continue;
			}
			$Content = $Revision->getContent( Revision::RAW );
			if ( ! $Content ) {
				continue;
			}
			$text = ContentHandler::getContentText( $Content );
			if ( ! $text ) {
				continue;
			}

			preg_replace_callback( '/[^{]{{([^#}{|]+)/', function ( $matches ) {
				$template = $matches[1];
				$template = trim( $template );
				$template = ucfirst( $template );
				$template = str_replace( '_', ' ', $template );
				$count = self::$templates[ $template ] ?? 0;
				$count++;
				self::$templates[ $template ] = $count;
			}, '@' . $text );
		}
		$templates = self::$templates;
		asort( $templates );
		//$templates = array_splice( $templates, 0, 100 );
		foreach ( $templates as $template => $count ) {
			echo $count . ' ' . $template . PHP_EOL;
		}
	}
}

$maintClass = ListTemplatesByUse::class;
require_once RUN_MAINTENANCE_IF_MAIN;