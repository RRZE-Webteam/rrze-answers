=== RRZE Answers ===
Contributors: rrze-webteam
Tags: faq, glossary, synonym, placeholder, shortcode, block, widget
Requires at least: 6.1
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.2.27
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Unified plugin for FAQs, glossary entries, synonyms, and placeholders with shortcode, block, widget, and REST API support.

== Description ==

RRZE Answers combines the functionality of the former RRZE FAQ, RRZE Glossary, and RRZE Synonym plugins into one solution.

It allows you to:
* Create and display FAQs, glossary entries, synonyms, and placeholders
* Synchronize content between websites in the FAU network
* Display entries via shortcodes, Gutenberg blocks, or widgets
* Filter and group entries by categories, tags, or domains
* Access content via the WordPress REST API (v2)
* Improve SEO with structured data output

Main features:
* Unified content management for FAQs, glossary entries, synonyms, and placeholders
* Flexible display options (accordion, A-Z index, tabs, tag cloud, grid)
* Cross-domain synchronization
* Multilingual output and language filtering
* REST API support

== Installation ==

1. Download the plugin.
2. Unzip the ZIP file.
3. Upload the `rrze-answers` folder to the `/wp-content/plugins/` directory of your WordPress installation.
4. Activate the plugin via the `Plugins` menu in WordPress.
5. Optional: Configure synchronization domains under `Settings > RRZE Answers`.

== Usage ==

=== Blocks ===

* RRZE FAQ Block
* RRZE FAQ Widget Block
* RRZE Glossary Block
* RRZE Placeholder Block 

=== Shortcodes ===

FAQ:

    [faq id="456,123"]
    [faq category="category-1"]
    [faq tag="tag-1,tag-2"]
    [faq category="category-1" tag="tag-2"]

Important FAQ attributes include:
* `glossary`, `category`, `tag`, `domain`, `id`
* `hide`, `masonry`, `search`, `lang`, `class`
* `sort`, `order`, `hstart`

Glossary:

    [glossary id="123,456"]
    [glossary category="category-1"]
    [glossary tag="tag-1,tag-2"]

Important Glossary attributes include:
* `register`, `category`, `tag`, `id`, `lang`
* `hide`, `show`, `class`, `sort`, `order`, `hstart`

Synonyms:

    [synonym id="123"]
    [synonym slug="bildungsministerium"]
    [fau_abbr id="987"]
    [fau_abbr slug="url"]

`[fau_abbr]` outputs `<abbr>` tags and supports language/pronunciation metadata when present.

Placeholders:

    [placeholder]
    [placeholder id="123"]
    [placeholder slug="semesterbeitrag"]
    [placeholder lang="de"]
    [placeholder id="123,456" lang="en"]

Placeholder attributes:
* `id` - one or more placeholder IDs (comma-separated)
* `slug` - display a placeholder by slug
* `lang` - filter by language meta value
* no attributes - display all published placeholders

Notes:
* If `slug` is set, it takes precedence over `id`.
* Output is rendered as placeholder content from the editor.

=== Synchronization Across Domains ===

External domains can be added and synchronized via:

    Settings > RRZE Answers > Domains
    Settings > RRZE Answers > Import

Synchronized entries behave like local entries and can be displayed via shortcode, block, or widget.

=== Widgets ===

* Answers Widget: show a specific or random FAQ or glossary entry
* Configurable options include layout and category selection

=== REST API (v2) ===

FAQ:
* `https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/faq`
* `https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/faq?filter[rrze_faq_tag]=Matrix`

Glossary:
* `https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/glossary`
* `https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/glossary?filter[rrze_glossary_category]=Dienste&filter[rrze_glossary_tag]=Sprache`


Pagination:
https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/

== License ==

This plugin is free software under GPLv2 or later.
