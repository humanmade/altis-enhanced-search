# Search

![](./assets/banner-search.png)

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

When used in conjunction with the [Media Rekognition](docs://media/image-recognition.md) feature, all images are processed for automatic keyword detection and stored in the search index too.

All documents that are uploaded to the media library are also parsed and indexed. For example, if you upload a PDF file, the PDF content will be read and included in the search index. Searches for keywords and phrases that are included in the document will be then be included in search results.

The follow document types are parsed and their content is added to the search index:

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
