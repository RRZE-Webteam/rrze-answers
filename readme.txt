=== RRZE FAQ ===
Contributors: rrze-webteam
Tags: faq, shortcode, block, widget, categories
Requires at least: 6.1
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 0.1.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Plugin for creating and synchronizing FAQs within the FAU network. Usable as a shortcode, block, or widget.

== Description ==

The plugin enables the creation of FAQs and synchronization with other FAU websites.

Features:
* Output of FAQs by category, tag, or domain
* Grouping as accordion or list
* Glossary as A-Z index, tabs, or tag cloud
* REST API support
* Support for multiple domains via synchronization
* Widget to display a random or fixed FAQ
* Shortcodes with extensive attributes
* Support for Gutenberg block

== Installation ==

1. Download the plugin.
2. Unzip the ZIP file.
3. Upload the `rrze-answers` folder to the `/wp-content/plugins/` directory of your WordPress installation.
4. Activate the plugin via the `Plugins` menu in WordPress.
5. Optional: Configure synchronization domains under `Settings > RRZE FAQ`.

== Usage ==

Examples of shortcodes:

    [faq]
    [faq category="category-1"]
    [faq tag="tag-1"]
    [faq id="123, 456"]
    [faq glossary="category tabs"]
    [faq glossary="tag tagcloud"]

More details on usage can be found in the `readme.md` file or the documentation.

== Frequently Asked Questions ==

= Can I display FAQs from other FAU websites? =
Yes. To do this, the domain must be added under `Settings > RRZE FAQ > Domains` and synchronization must be executed.

= Is there a widget? =
Yes. It is available under `Appearance > Widgets` as “FAQ Widget.”

= How does the REST API work? =
The plugin supports the WordPress REST API v2 with extended filtering options.

== License ==

This plugin is free software under GPLv2 or later.
