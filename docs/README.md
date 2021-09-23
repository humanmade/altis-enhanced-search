# Search

![](./assets/banner-search.png)

The Search module provides a mirrored Elasticsearch index of all CMS content that is optimized for search relevance, speed and accuracy. This operates at a higher level than the primary datastore (MySQL) and is the recommended solution for searching.

The benefits of using Elasticsearch over MySQL search queries include:

- Speed
- Advanced text analysis
  - Removing HTML
  - Language dependent word stemming and stopwords
  - Language dependent tokenisation
- Configurable relevancy scores
- Fuzzy matching
- Synonyms
- Aggregations for faceted search and statistics

The default Search index and related functionality is provided by the [ElasticPress plugin](https://github.com/10up/ElasticPress) and the multilingual support is derived from [the WordPress.com Elasticsearch library](https://github.com/Automattic/wpes-lib).

If you do not wish to use the search module it can be deactivated via your config:

```json
{
	"extra": {
		"altis": {
			"modules": {
				"search": {
					"enabled": false
				}
			}
		}
	}
}
```

**Note:** turning this module off does not remove the Elasticsearch server. This can still be used for [Native Analytics](docs://analytics/native/README.md) and any custom use cases.

Content that is indexed in the search index by default:

- Posts
- Pages
- Media
- Custom Post Types (registered with `show_in_search` or `public`)
- Post Meta
- Post Terms
- Post Author
- Users
- User Meta
- Terms
- Term Meta

**Note:** Post meta that is "protected" - i.e. has a key beginning with `_` - will not be indexed automatically. To index these fields, use the `ep_prepare_meta_allowed_protected_keys` filter. It will accept a value of boolean `true` (which will index *all* protected meta) or an array containing the keys of specific protected meta fields you want to index.

When used in conjunction with the [Media Rekognition](docs://media/image-recognition.md) feature, all images are processed for automatic keyword detection and stored in the search index too.

## CMS Query Integration
The method for determining whether CMS queries are handled by Elasticsearch or MySQL uses sensible defaults, but can also be controlled in more granular ways through code. Special handling is required for supporting partial search terms common in autosuggest or "typeahead" interfaces.

[Find out how to control Elasticsearch query integration and how to use autosuggest search here](./cms-query-integration.md).

## Search Configuration
The default search behavior can be tuned to allow for stricter or more permissive matching, as well as enabling advanced search query capabilities such as using quoted strings for exact matches. You can also tune the relevancy of specific fields and how fuzzy matching works.

See [Search Configuration](./search-configuration/README.md) for full details.

### Custom User Dictionaries
A subset of search configuration is the ability to upload [custom user dictionaries](./search-configuration/custom-dictionaries.md) for adding synonyms, stop words and custom text analysis for Japanese to further tune and improve search results.

## Document Indexing
All documents that are uploaded to the media library can also be parsed and indexed. For example, if you upload a PDF file, the PDF content will be read and included in the search index. Searches for keywords and phrases that are included in the document will be then be included in search results.

The following document types are parsed and their content is added to the search index:

- PDF
- PPT
- PPTX
- XLS
- XLSX
- DOC
- DOCX

To enable the indexing of document content, set the `modules.search.index-documents` setting to `true`.

## Search Index Modification
It is also possible to modify the specific fields stored for each post to provide extra search data that is not included by default. See [Search Index Modification](posts-index-modification.md) for details.

## Using Elasticsearch
Elasticsearch is used to provide the search index, as such as a developer you can make direct use of Elasticsearch for advanced feature development. See [Using Elasticsearch](using-elasticsearch.md) for details.

## Using Google Analytics (GA)
Google Analytics can be configured to ingest search queries for further analysis and insights. This will inform future weightings and search configuration modifications. When configuring GA, the default query parameter value is `s`, see [Google Analytics - Set up Site Search](https://support.google.com/analytics/answer/1012264) for more information.

## Additional Configuration Options
The following options can be enabled/disabled via the search configuration.

- `"related-posts": true|false (default)`
- `"facets": true|false|['match-type' => "all" (default)|...]`
- `"woocommerce": true|false (default)`
- `"autosuggest": true|false (default)`
- `"users": true (default)|false`
- `"terms": true (default)|false`

### Related Posts
To find related posts leveraging Elastic Search use the `ep_find_related()` function. The function requires a single parameter ( `$post_id` ) with another optional parameter ( `$return` ). The `$post_id` will be used to find the posts that are related to it, with `$return` specifying the number of related posts to return, which defaults to 5.

If an out of the box solution is desired, a widget `ElasticPress - Related Posts` is created that can be added to your site's sidebar. In order for the widget to work correctly it needs to be added to the sidebar which will be displayed for a single post.

### Facets
Facets are a feature in ElasticPress which add control to filter content by one or more taxonomies. A widget can be added so when viewing a content list (archive), the taxonomy and all of its terms will be displayed. This will allow a vistors to further filter content.

Depending on the configuration specified for `facets`, if the `match-type` property is set to `any`, it will force the results to match any selected taxonomy term. If set to `all`, it will match to results with all of the selected terms.

### Search Form Auto Suggest
This feature enhances search forms on the website to show a dropdown list of suggestions as users type.
