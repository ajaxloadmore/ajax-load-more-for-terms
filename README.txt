=== Ajax Load More for Terms ===

Contributors: dcooney, connekthq
Author: Darren Cooney
Author URI: https://connekthq.com/
Plugin URI: https://connekthq.com/ajax-load-more/extensions/terms/
Donate link: https://connekthq.com/donate/
Tags: wp_term_query, get_terms, category, tag, taxonomy, terms, infinite scroll, load more
Requires at least: 4.0
Tested up to: 6.2
Stable tag: 1.1
License: GPLv2 or later
License URI: http://gnu.org/licenses/gpl-2.0.html

Ajax Load More extension that adds compatibility for infinite scrolling WordPress terms using a `term_query`.

== Description ==

**Ajax Load More for Terms** provides additional functionality for infinite scrolling Taxonomy Terms with Ajax Load More using the [WP_Term_query](https://developer.wordpress.org/reference/classes/wp_term_query/).


**[View Documentation](https://connekthq.com/plugins/ajax-load-more/extensions/terms/)**


= Shortcode Parameters =


The following Ajax Load More shortcode parameters are available when the Advanced Custom Fields extension is activated.

*   **term_query** - Enable `term_query` functionality. (true/false)
*   **term_query_taxonomy** - The taxonomy to query. Default = `null`
*   **term_query_number** - The amount . Default = `5`
*   **term_query_hide_empty** - Whether to hide terms not assigned to any posts. Default = `true`


= Example Shortcode =

    [ajax_load_more repeater="default" term_query="true" term_query_taxonomy="movie" term_query_number="5"]


== Frequently Asked Questions ==

= What version of Ajax Load More is required? =
You must have v5.2+ of Ajax Load More installed.

= How do I use this extension? =
Once installed, visit the Ajax Load More Shortcode Builder and build a custom shortcode specifying the Term Query.


== Screenshots ==



== Installation ==

= Uploading in WordPress Dashboard =
1. Navigate to the 'Add New' in the plugins dashboard
2. Navigate to the 'Upload' area
3. Select `ajax-load-more-for-terms.zip` from your computer
4. Click 'Install Now'
5. Activate the plugin in the Plugin dashboard

= Using FTP =
1. Download `ajax-load-more-terms.zip`.
2. Extract the `ajax-load-more-for-terms` directory to your computer.
3. Upload the `ajax-load-more-for-terms` directory to the `/wp-content/plugins/` directory.
4. Ensure Ajax Load More is installed prior to activating the plugin.
5. Activate the plugin in the WP plugin dashboard.


== Changelog ==

= 1.1 - June 11, 2023 = 
* UPDATE: Updated to add compatibility with Cache Add-on 2.0 and Ajax Load More 6.0.

= 1.0 - March 13, 2020 =
* Initial Release.

== Upgrade Notice ==
* None
