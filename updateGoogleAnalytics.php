<?php

/**
 * This script gets the pageviews for each page from Google Analytics
 * and stores it in the database for later use
 * This script requires Extension:Metadata to store the data and then use it
 * This script is designed to run once a month via cronjob
 */

// Load dependencies
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/../w/maintenance/Maintenance.php';
use MediaWiki\MediaWikiServices;

class UpdateGoogleAnalytics extends Maintenance {

	public function execute() {

		// Connect to Google Analytics
		$client = new Google_Client();
		$client->setApplicationName( 'Appropedia Analytics' );
		$client->setAuthConfig( '/home/appropedia/google-cloud-credentials.json' );
		$client->setScopes( [ 'https://www.googleapis.com/auth/analytics.readonly' ] );
		$analytics = new Google_Service_Analytics( $client );

		// Connect to the first account
		$accounts = $analytics->management_accounts->listManagementAccounts();
		$items = $accounts->getItems();
		$items or exit( 'No accounts found for this user' . PHP_EOL );
		$account = $items[0]->getId();

		// Connect to the first property
		$properties = $analytics->management_webproperties->listManagementWebproperties( $account );
		$items = $properties->getItems();
		$items or exit( 'No properties found for this user' . PHP_EOL );
		$property = $items[0]->getId();

		// Connect to the first view (profile)
		$profiles = $analytics->management_profiles->listManagementProfiles( $account, $property );
		$items = $profiles->getItems();
		$items or exit( 'No views (profiles) found for this user' . PHP_EOL );
		$profile = $items[0]->getId();

		// Run for all the non-redirect mainspace pages
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
		$result = $dbr->select( 'page', 'page_title', [ 'page_namespace' => 0, 'page_is_redirect' => 0 ], __METHOD__, [ 'ORDER BY' => 'page_id DESC' ] );
		foreach ( $result as $row ) {

			// Don't abuse Google Analytics
			// According to https://developers.google.com/analytics/devguides/reporting/core/v4/limits-quotas#reporting_apis
			// we can do 10,000 requests per day, or one every 10 seconds
			sleep( 10 );

			// Build the filters
			$title = $row->page_title;
			$escapedTitle = str_replace( ',', '\,', str_replace( ';', '\;', $title ) ); // Escape reserved characters
			$filters = "ga:pagePath==/$escapedTitle";
			$Title = Title::newFromText( $title );
			$Redirects = $Title->getRedirectsHere();
			foreach ( $Redirects as $Redirect ) {
				$redirect = $Redirect->getPrefixedDBKey();
				$escapedRedirect = str_replace( ',', '\,', str_replace( ';', '\;', $redirect ) ); // Escape reserved characters
				$filters .= ",ga:pagePath==/$escapedRedirect";
			}

			// Build the query
			// First day with data: 2016-08-23
			// See https://developers.google.com/analytics/devguides/reporting/core/v3/coreDevguide
			$results = $analytics->data_ga->get( "ga:$profile", '2016-08-23', 'today', 'ga:pageviews', [ 'filters' => $filters ] );
			$rows = $results->getRows();
			if ( !$rows ) {
				continue;
			}

			// Aggregate the pageviews
			$pageviews = 0;
			foreach ( $rows as $row ) {
				$pageviews += array_shift( $row );
			}

			// Store the pageviews in the database
			$id = $Title->getArticleID();
			Metadata::set( $id, 'GoogleAnalyticsPageviews', $pageviews );

			// Output a log
			$this->output( $id . ' ' . $Title->getFullURL() . ' -> ' . $pageviews . PHP_EOL );
		}
	}
}

$maintClass = UpdateGoogleAnalytics::class;
require_once RUN_MAINTENANCE_IF_MAIN;