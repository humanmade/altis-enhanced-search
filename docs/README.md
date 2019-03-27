# Search

The Search module provides a mirrored index of all CMS content that is optimized for search relevance, speed and accuracy. The Search module overrides the default search functionality to query the specialized search index in place of a standard MySQL query. This means all default search operations using the CMS search APIs for posts such as `WP_Query`, `get_posts`, `get_search_form()` and the REST API search endpoints will transparently make use of the search index.

The default Search index and related functionality is provided by the [ElasticPress plugin](https://github.com/10up/ElasticPress).

Content that is indexed in the search index by default:

- Posts
- Pages
- Media
- Custom Post Types (registered with `show_in_search` or `public`)
- Post's Meta
- Post's Terms
- Post's Author

When used in conjunction with the Media Rekognition feature, all images are processed for automatic keyword detection and stored in the search index too.

It is also possible to modify the specific fields stored for each post to provide extra search data that is not included by default. See [Search Index Modification](posts-index-modification.md) for details.

Elasticsearch is used to provide the search index, as such as a developer you can make direct use of Elasticsearch for advanced feature development. See [Using Elasticsearch](using-elasticsearch.md) for details.
