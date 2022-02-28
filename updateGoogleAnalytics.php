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

			// Don't abuse Google Analytics
			// https://developers.google.com/analytics/devguides/reporting/core/v4/limits-quotas#analytics_reporting_api_v4
			sleep( 10 );

			// Query Google Analytics
			global $wgArticlePath;
			$page = str_replace( '$1', $title, $wgArticlePath );
			$pageviews = GoogleAnalyticsMetricsHooks::getMetric( [ 'page' => $page, 'metric' => 'pageviews' ] );
			if ( !is_int( $pageviews ) ) {
				continue;
			}
			foreach ( $Title->getRedirectsHere() as $Redirect ) {
				$redirect = $Redirect->getPrefixedDBKey();
				$redirect = str_replace( '$1', $redirect, $wgArticlePath );
				$redirectviews = GoogleAnalyticsMetricsHooks::getMetric( [ 'page' => $redirect, 'metric' => 'pageviews' ] );
				if ( is_int( $redirectviews ) ) {
					$pageviews += $redirectviews;
				}
			}
			if ( !$pageviews ) {
				continue;
			}

			// Store the pageviews in the database
			$this->output( $id . ' ' . $Title->getFullURL() . ' -> ' . $pageviews . PHP_EOL );
			Metadata::set( $id, 'GoogleAnalyticsPageviews', $pageviews );
		}
	}
}

$maintClass = UpdateGoogleAnalytics::class;
require_once RUN_MAINTENANCE_IF_MAIN;