<?php
/**
 * This script gets the pageviews for each page from Google Analytics
 * and stores it in the database for later use
 * This script requires Extension:GoogleAnalyticsMetrics and Extension:Metadata
 * This script is run once a month by a cronjob
 */

require_once __DIR__ . '/../w/maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;

class UpdateGoogleAnalytics extends Maintenance {

	public function execute() {

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$result = $dbr->select( 'page', 'page_title', [ 'page_namespace' => 0, 'page_is_redirect' => 0 ] );
		foreach ( $result as $row ) {

			$title = $row->page_title;
			$Title = Title::newFromText( $title );
			$id = $Title->getArticleID();
			if ( !$id ) {
				continue;
			}

			// Query Google Analytics
			global $wgArticlePath;
			$page = str_replace( '$1', Title::newFromText( $title )->getPrefixedDBKey(), $wgArticlePath );
			$pageviews = GoogleAnalyticsMetricsHooks::getMetric( [ 'page' => $page, 'metric' => 'pageviews' ] );
			foreach ( $Title->getRedirectsHere() as $Redirect ) {
				$redirect = $Redirect->getFullText();
				$page = str_replace( '$1', Title::newFromText( $redirect )->getPrefixedDBKey(), $wgArticlePath );
				$pageviews += GoogleAnalyticsMetricsHooks::getMetric( [ 'page' => $page, 'metric' => 'pageviews' ] );
			}
			if ( !$pageviews ) {
				continue;
			}

			// Store the pageviews in the database
			$this->output( $id . ' ' . $Title->getFullURL() . ' -> ' . $pageviews . PHP_EOL );
			Metadata::set( $id, 'GoogleAnalyticsPageviews', $pageviews );

			// Don't abuse Google Analytics
			// https://developers.google.com/analytics/devguides/reporting/core/v4/limits-quotas#analytics_reporting_api_v4
			sleep( 10 );
		}
	}
}

$maintClass = UpdateGoogleAnalytics::class;
require_once RUN_MAINTENANCE_IF_MAIN;