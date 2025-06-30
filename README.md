[![Aktuelle Version](https://img.shields.io/github/package-json/v/rrze-webteam/rrze-faq/main?label=Version)](https://github.com/RRZE-Webteam/rrze-faq)
[![Release Version](https://img.shields.io/github/v/release/rrze-webteam/rrze-faq?label=Release+Version)](https://github.com/rrze-webteam/rrze-faq/releases/)
[![GitHub License](https://img.shields.io/github/license/rrze-webteam/rrze-faq)](https://github.com/RRZE-Webteam/rrze-faq)
[![GitHub issues](https://img.shields.io/github/issues/RRZE-Webteam/rrze-faq)](https://github.com/RRZE-Webteam/rrze-faq/issues)
# RRZE FAQ

Plugin for creating FAQs and synchronizing them with other FAU websites. Usable as a shortcode, block, or widget.

## General Information

The plugin can be used to create FAQs and to synchronize FAQs from websites within the FAU network.  
You can filter by categories and tags.  
The layout can be configured to display an A–Z index, categories or tags as links, or as a tag cloud.  
Categories and tags are grouped into accordions. It is also possible to output individual FAQs directly.

A widget is also available. You can configure the display duration and whether to show a specific or a random FAQ from a selected category.

## Using the Shortcode

```html
[faq id="456, 123"]
[faq category="category-1, category-2"]
[faq tag="tag-1, tag-2"]
[faq category="category-1" tag="tag-1, tag-2"]
[faq category="category-1" domain="domain-1, domain-2"]
```

## All Shortcode Attributes

```html
[faq 
glossary=".."
category=".."
tag=".."
domain=".."
id=".."
hide=".."
show=".."
class=".."
sort=".."
order=".."
hstart=".."
]
```

All attributes are optional.

## Explanation and Values for Shortcode Attributes

All attributes are optional.

**glossary**: Defines how the FAQ items are grouped. Possible values are `"category"` or `"tag"`.  
If omitted, no grouping is applied. You can also define the visual style of the glossary:  
- `"a-z"`: Alphabetical index (default)  
- `"tabs"`: Glossary terms as tabs  
- `"tagcloud"`: Terms shown with font size proportional to their frequency

**category**: Specifies the categories for which matching FAQs should be displayed.  
Multiple categories can be listed using their slugs (as found under `FAQ → Categories`) and separated by commas.  
You can also filter by data source:  
Example: `category="rrze:general, info"` filters for the "general" category from the "rrze" source and any "info" category regardless of source.

**tag**: Specifies the tags for which matching FAQs should be shown.  
Use the tag slugs from `FAQ → Tags`, separated by commas.  
Filtering by source is also possible:  
Example: `tag="rrze:keyword1, keyword2"` filters for "keyword1" from source "rrze", and "keyword2" from any source.

**domain**: Filters FAQs by their data source.  
Multiple domains can be listed, separated by commas.

**id**: Outputs one or more specific FAQs by ID.  
You can find the ID in the admin panel under `FAQ → All FAQs` or in the "Insert into pages/posts" box when editing an FAQ.  
The output order follows the order of the IDs listed.

**hide**: Controls which default elements should be hidden.  
- `"accordion"`: Shows FAQs as plain question/answer instead of collapsible panels  
- `"title"`: Hides the FAQ question  
- `"glossary"`: Disables the glossary display  
Default: accordion view is enabled

**masonry**:  
- `"true"`: Displays FAQs in a grid (masonry) layout  
- `"false"`: Displays FAQs in the default stacked layout  
By default, the grid layout is disabled.

**class**: Allows you to set the color of the left border of the accordion.  
Possible values: `"fau"` (default), or faculty identifiers like `"med"`, `"nat"`, `"rw"`, `"phil"`, or `"tf"`.  
You can also add additional CSS classes separated by spaces.

**sort**: Controls sorting of the output.  
- `"title"`: Sort alphabetically by title (default)  
- `"id"`: Sort by FAQ ID  
- `"sortfield"`: Sort using the custom field defined per FAQ, then by title

**order**: Determines the sort direction.  
- `"asc"`: Ascending (default)  
- `"desc"`: Descending

**hstart**: Defines the heading level for the first title.  
Default is `2`, which renders headings as `<h2>`.

## Examples

```html
[faq glossary="tag tagcloud"]
[faq category="slug-of-category"]
[faq category="category" tag="Tag1, Tag2"]
[faq id="456, 987, 123" hide="glossary"]
[faq glossary="category tabs" tag="Tag1" show="expand-all-link" order="desc"]
```

## FAQs from Other Domains

External domains must be added under  
**Settings → RRZE FAQ → "Domains" tab**.  
Synchronization is carried out via the **"Synchronization"** tab.

## Using the Widget

In `/wp-admin/widgets.php`, the widget is available as **"FAQ Widget"**.  
You can configure:

- Display duration
- A specific or a random FAQ from a category

## Using the REST API (v2)

**Examples:**

- All FAQs:  
  https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/faq

- Filtered by tag:  
  https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/faq?filter[faq_tag]=Matrix

- Multiple tags:  
  https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/faq?filter[faq_tag]=Matrix%2BAccounts

- Category:  
  https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/faq?filter[faq_category]=Dienste

- Category + tag:  
  https://www.anleitungen.rrze.fau.de/wp-json/wp/v2/faq?filter[faq_category]=Dienste&filter[faq_tag]=Sprache

- Pagination:  
  https://developer.wordpress.org/rest-api/using-the-rest-api/pagination/
