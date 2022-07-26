<?php

/**
 * This script gets the pageviews for each page from Google Analytics
 * and stores it in the database for later use
 * This script requires Extension:GoogleAnalyticsMetrics to get the pageviews
 * and Extension:Metadata to store them and then use them
 * This script is designed to be run once a month via cronjob
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
			// according to https://developers.google.com/analytics/devguides/reporting/core/v4/limits-quotas#reporting_apis
			// we can do 10,000 requests per day, or one every 10 seconds
			sleep( 10 );

			// Query Google Analytics
			global $wgArticlePath;
			$page = str_replace( '$1', $title, $wgArticlePath );
			$pageviews = GoogleAnalyticsMetricsHooks::getMetric( [ 'page' => $page, 'metric' => 'pageviews' ] );
			if ( !is_numeric( $pageviews ) ) {
				continue;
			}

			// Add the pageviews of all the redirects
			// some pages lived a long time under another title so this is relevant
			foreach ( $Title->getRedirectsHere() as $Redirect ) {
				sleep( 10 ); // Again, don't abuse Google Analytics
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