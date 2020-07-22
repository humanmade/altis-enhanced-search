# Search

![](./assets/banner-search.png)

The Search module provides a mirrored index of all CMS content that is optimized for search relevance, speed and accuracy. The Search module overrides the default search functionality to query the specialized search index in place of a standard MySQL query. This means all default search operations using the CMS search APIs for posts such as `WP_Query`, `get_posts`, `get_search_form()` and the REST API search endpoints will transparently make use of the search index.

The default Search index and related functionality is provided by the [ElasticPress plugin](https://github.com/10up/ElasticPress) and the multilingual support is derived from [the WordPress.com ElasticSearch library](https://github.com/Automattic/wpes-lib).

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

Content that is indexed in the search index by default:

- Posts
- Pages
- Media
- Custom Post Types (registered with `show_in_search` or `public`)
- Post's Meta
- Post's Terms
- Post's Author

When used in conjunction with the [Media Rekognition](docs://media/image-recognition.md) feature, all images are processed for automatic keyword detection and stored in the search index too.

All documents that are uploaded to the media library are also parsed and indexed. For example, if you upload a PDF file, the PDF content will be read and included in the search index. Searches for keywords and phrases that are included in the document will be then be included in search results.

The following document types are parsed and their content is added to the search index:

- PDF
- PPT
- PPTX
- XLS
- XLSX
- DOC
- DOCX

To disable the indexing of document content, set the `modules.search.index-documents` settings to `false`.

It is also possible to modify the specific fields stored for each post to provide extra search data that is not included by default. See [Search Index Modification](posts-index-modification.md) for details.

Elasticsearch is used to provide the search index, as such as a developer you can make direct use of Elasticsearch for advanced feature development. See [Using Elasticsearch](using-elasticsearch.md) for details.

## Search Configuration
The default search behaviour can be modified to allow for stricter or more permissive matching as well as enabling advanced search query capabilities such as quoted strings for exact matches.

See [Search Configuration](./search-configuration.md) for full details.

## Additional Configuration Options
The following options can be enabled/disabled via the search configuration.

- `"related-posts": true|false (default)`
- `"facets": true|false|['match-type' => "all" (default)|...]`
- `"woocommerce": true|false (default)`
- `"autosuggest": true|false (default)`
- `"index-documents": true (default) |false`

### Related Posts
To find related posts leveraging Elastic Search use the `ep_find_related()` function. The function requires a single parameter ( `$post_id` ) with another optional parameter ( `$return` ). The `$post_id` will be used to find the posts that are related to it, with `$return` specifying the number of related posts to return, which defaults to 5.

If an out of the box solution is desired, a widget `ElasticPress - Related Posts` is created that can be added to your site's sidebar. In order for the widget to work correctly it needs to be added to the sidebar which will be displayed for a single post.

### Facets
Facets are a feature in ElasticPress which add control to filter content by one or more taxonomies. A widget can be added so when viewing a content list (archive), the taxonomy and all of its terms will be displayed. This will allow a vistors to further filter content.

Depending on the configuration specified for `facets`, if the `match-type` property is set to `any`, it will force the results to match any selected taxonomy term. If set to `all`, it will match to results with all of the selected terms.

### Auto Suggest
The default auto suggest functionality has been modified but does not effect the default WP search form template.

In addition to the default auto suggest endpoint, an additional endpoint was added (`/autosuggest/`). This endpoint accepts as json object to modify the parameters that are forwarded to ElasticPress for suggesting posts.
