=== RRZE Answers ===
Contributors: rrze-webteam
Tags: faq, glossary, synonym, placeholder, shortcode, block, widget
Requires at least: 7.0
Tested up to: 7.1.4
Requires PHP: 8.2
Stable tag: 2.0.3
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

Version 2.0 requires WordPress 7.0 or later and PHP 8.2 or later. Existing
content remains supported when upgrading. Saving blocks uses the 2.x attribute
format; replacing only the plugin files with a 1.x version is not supported.
Recovery to 1.x requires restoring a matching pre-upgrade database backup.
The completed CMS migration must not be repeated.

Update every consuming site to 2.x before resuming cross-site synchronization.
The 2.x consumer supports both old and new producers; old consumers can confuse
source taxonomy IDs with their own local IDs.

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


=== Inserting in the text editor ===
The text editor provides a dedicated menu entry for inserting synonyms. From there, you can select any available synonym from a list and insert it directly into your content.
In the classic text editor (TinyMCE), placeholders can be inserted via the shortcode menu/button. This inserts a `[placeholder: ...]` shortcode.


=== Shortcodes ===

Embeds only display published entries of the requested content type. Drafts,
private entries and Trash are omitted, including explicit ID selections.
Password-protected entries require the visitor to have entered their password.

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
Synonyms and abbreviations support comma-separated IDs and a `lang` filter.
Their text and HTML attributes are escaped as plain text.

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
* Legacy encoded HTML is decoded and sanitized before WordPress renders blocks and shortcodes.
* Inline placeholders with an unavailable ID do not display their saved text snapshot.
  Legacy markers without an ID retain their sanitized text fallback.

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

== Changelog ==

= 2.0.3 =
* Repair required legacy FAQ parents even when their slug collides with an existing FAQ category. Keep the existing category's slug and give only the migrating legacy parent a unique slug based on <old-slug>-legacy-<term-id>, with a further suffix if needed.
* Preserve category identities, parent links, metadata and FAQ assignments without merging categories. The reported 24-category structure reaches 26 categories after resolving the parent slug collision; 36 unrelated legacy categories remain unchanged.
* Retain completed repair states and existing 24-hour retry deadlines. No network-wide rerun, external requests or import-configuration changes are introduced.

= 2.0.2 =
* Restore required legacy FAQ parent categories omitted by the former FAQ migration, preserving category IDs and content assignments.
* Run the repair once per visited site, with concurrency protection, interruption recovery and site-local cache invalidation; no network-wide migration or external requests.
* Report unresolved conflicts only to super administrators in the affected site's dashboard. Import configuration, FAQ content and other taxonomies remain unchanged.
* Stop before category changes when a required legacy parent's slug collides with another FAQ category; automatic slug disambiguation is added in 2.0.3.

== License ==

This plugin is free software under GPLv2 or later.
