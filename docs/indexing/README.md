# Indexing

The indexing process is how Altis adds, updates, and deletes data in your Elasticsearch indexes.

Altis automatically indexes your content as it changes to keep search indexes in sync with the original data stored in the database.
Data can also be [manually indexed via re-indexing](reindexing.md).

## Data Types and Fields

By default, Altis indexes the following types of content from your site:

- Posts
- Pages
- Media
- Custom Post Types (registered with `show_in_search` or `public`)
- Post Meta
- Post Terms
- Post Author
- Terms
- Term Meta

The following data is not indexed by default but can be enabled via your config:

- Comments
- Comment Meta

**Note:** Post meta that is "protected" - i.e. has a key beginning with `_` - will not be indexed automatically. To index these
fields, use the `ep_prepare_meta_allowed_protected_keys` filter. It will accept a value of boolean `true` (which will index *all*
protected meta) or an array containing the keys of specific protected meta fields you want to index.

When used in conjunction with the [Media Rekognition](docs://media/image-recognition.md) feature, all images are processed for
automatic keyword detection and stored in the search index too.

## User indexing

Older versions of the Enhanced Search module indexed user data by default, however this was removed from ElasticPress in version 5.
If you still wish to index user data, you can add the 10Up plugin [ElasticPress Labs](https://github.com/10up/ElasticPressLabs)
which includes a user indexing feature along with several other specialized features.

## Document Indexing

All documents that are uploaded to the media library can also be parsed and indexed. For example, if you upload a PDF file, the PDF
content will be read and included in the search index. Searches for keywords and phrases that are included in the document will be
then be included in search results.

The following document types are parsed and their content is added to the search index:

- PDF
- PPT
- PPTX
- XLS
- XLSX
- DOC
- DOCX

To enable the indexing of document content, set the `modules.search.index-documents` setting to `true`.
