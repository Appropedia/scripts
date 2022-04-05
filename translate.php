<?php

require_once __DIR__ . '/w/maintenance/Maintenance.php';

use MediaWiki\MediaWikiServices;

class AppropediaTranslatorScript extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Translate a page using Google Translate' );
		$this->addArg( 0, 'Title of the page to translate', false );
	}

	public function execute() {

		$page = $this->getArg();

		$language = 'es';

		//$wiki = new EasyWiki( 'https://mediawiki.solutions/w/api.php', 'Sophivorus@Bot', '0vf4jntsihi1sf3jocinu0r3is8e7usf' );
		$wiki = new EasyWiki( 'https://www.appropedia.org/w/api.php', 'Sophivorus@Ant_bot', '7v9lkpmqpj8ieo8bed7co1u1h4r77522' );

		// Get the wikitext of the page
		$wikitext = $wiki->getWikitext( $page );
		var_dump( $wikitext ); exit; // Uncomment to debug

		// Convert the wikitext into HTML
		$query = [
			'action' => 'visualeditor',
			'paction' => 'parsefragment',
			'page' => $page,
			'wikitext' => $wikitext,
		];
		$html = $wiki->get( $query, 'content' );
		//var_dump( $html ); exit; // Uncomment to debug

		// Remove unnecessary markup
		// @todo Proper DOM parsing
		$DOM = new DOMDocument;
		$html = preg_replace( "/ data-parsoid='[^']+'/", '', $html );
		$html = preg_replace( "/<style.*?>.*?<\/style>/s", '', $html ); 
		$html = preg_replace( "/<div(.*?)data-mw(.*?)>.*<\/div>/s", '<div$1data-mw$2></div>', $html ); // @todo nested divs
		//var_dump( $html ); exit; // Uncomment to debug

		// Translate the HTML
		$translation = $this->googleTranslate( $html, $language );
		//var_dump( $translation ); exit; // Uncomment to debug

		// Save the translated HTML
		$query = [
			'formatversion' => 2,
			'action' => 'visualeditoredit',
			'paction' => 'save',
			'page' => "$page/$language",
			'html' => $translation,
		];
		$data = $wiki->post( $query );
		//var_dump( $data ); exit;
	}

	/**
	 * Send strings to Google Translate
	 */
	public function googleTranslate( $contents, $targetLanguageCode ) {
		global $wgGoogleCloudSDK;

		// Build the request
		$payload = json_encode( [
			'target_language_code' => $targetLanguageCode,
			'contents' => $contents
		] );

		$token = exec( "$wgGoogleCloudSDK auth print-access-token" );

		$headers = [
			"Authorization: Bearer $token",
			'Content-Type: application/json'
		];

		// Do the request
		$curl = curl_init( 'https://translation.googleapis.com/v3beta1/projects/wikimix/locations/global:translateText' );
		curl_setopt( $curl, CURLOPT_POST, true );
		curl_setopt( $curl, CURLOPT_POSTFIELDS, $payload );
		curl_setopt( $curl, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		$json = curl_exec( $curl );
		curl_close( $curl );

		// Process the output and return it
		$data = json_decode( $json );
		if ( ! property_exists( $data, 'translations' ) ) {
			var_dump( $data );
			exit;
		}

		// Unwrap and return the translation
		$translation = $data->translations;
		$translation = array_shift( $translation );
		$translation = $translation->translatedText;
		$translation = html_entity_decode( $translation, ENT_QUOTES, 'UTF-8' );
		return $translation;
	}
}

$maintClass = AppropediaTranslatorScript::class;
require_once RUN_MAINTENANCE_IF_MAIN;