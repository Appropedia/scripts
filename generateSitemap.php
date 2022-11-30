<?php

/**
 * This script regenerates the sitemap and pings Google and Bing
 * This script is run once a day by a cronjob
 */

exec( '/usr/local/bin/php /home/appropedia/public_html/w/maintenance/generateSitemap.php --identifier appropedia --fspath /home/appropedia/public_html/sitemap/ --urlpath=/sitemap --server=https://www.appropedia.org --compress=no' );

file_get_contents( 'https://www.google.com/webmasters/sitemaps/ping?sitemap=https://www.appropedia.org/sitemap/sitemap-index-appropedia.xml' );

file_get_contents( 'https://www.bing.com/webmaster/ping.aspx?sitemap=https://www.appropedia.org/sitemap/sitemap-index-appropedia.xml' );