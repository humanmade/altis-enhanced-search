# Search

![Search Banner](./assets/banner-search.png)

The Search module provides a mirrored Elasticsearch index of all CMS content that is optimized for search relevance, speed and
accuracy. This operates at a higher level than the primary data store (MySQL) and is the recommended solution for searching.

The benefits of using Elasticsearch over MySQL search queries include:

- Speed
- Advanced text analysis
  - Removing HTML
  - Language dependent word stemming and stopwords
  - Language dependent tokenization
- Configurable relevancy scores
- Fuzzy matching
- Synonyms
- Aggregations for faceted search and statistics

The default Search index and related functionality is provided by the [ElasticPress plugin](https://github.com/10up/ElasticPress)
and the multilingual support is derived from [the WordPress.com Elasticsearch library](https://github.com/Automattic/wpes-lib).

## Support

Altis provides the Search module and underlying Elasticsearch server to facilitate search on your site, with default configuration
to meet a majority of use cases.

Using developer features of the Search module **requires understanding how Elasticsearch works**. We recommend reading
the [Elasticsearch guide by Elastic](https://www.elastic.co/guide/en/elasticsearch/).

Modifying and tuning search queries for relevance is a subjective process. Additionally, much like writing complex database queries,
building custom Elasticsearch queries requires a deep understanding of how Elasticsearch works.

Guides contained in this documentation are on a best-effort basis; for help with tuning or customising search results, the Altis
team can help you find a partner to facilitate your use. Altis support cannot help with tuning results, tweaking configuration, or
writing custom Elasticsearch queries.

## Terminology

Altis includes the Search module (`altis/enhanced-search`) which is built upon a WordPress plugin called ElasticPress, made by our
friends at [10up](https://10up.com). Altis adds additional functionality, including deep integration into the Altis platform.

Altis Cloud includes Elasticsearch backend servers, which are a database tuned specifically for search. The Search module sets up a
connection to the Elasticsearch servers.

When content is created or updated, that content is **indexed** into Elasticsearch. This works similar to caching, where a copy of
your original data is stored within Elasticsearch in an **index** (effectively, a database table). Unlike your original data, the
index can also contain additional data just for search, including generated or rendered data. Each item of content is stored as a *
*document** which has **fields** containing the data.

When a user searches for content on your site, Altis converts this into a **query**. The query is run in Elasticsearch against your
indexed content, which generates results with a **relevancy score**. Relevancy scores are based on the index configuration, field
configuration, the query being run, and the indexed data.

## Indexing

The indexing process is performed automatically for you by Altis,
indexing [most forms of content on your site](./indexing/README.md). Content can
be [reindexed if necessary](./indexing/reindexing.md), and [additional data can be indexed](./indexing/additional-data.md).

## Querying

Altis [automatically integrates with search queries](./querying/cms-integration.md), as well as
providing [autosuggest functionality automatically for your search forms](./querying/autosuggest.md).

Developers can also use [custom queries](./querying/custom-queries.md) for advanced feature development.

## Configuration

Altis provides [various configuration options](./configuration/README.md) to allow adjusting ElasticPress and Elasticsearch
behaviour.

You can also use these options to [tune relevancy scoring](./configuration/tuning.md),
including [date-based "decay"](./configuration/date-decay.md).

Users can also use the [custom user dictionary settings](./configuration/custom-dictionaries.md) to adjust how text is analyzed,
including support for synonyms, stop words and custom text analysis for Japanese.

## Disabling Search

The Search module works by overriding default WordPress search, which uses MySQL full-text search. If you would prefer to use MySQL
search, you can deactivate the search module via your config:

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

**Note:** turning this module off does not remove the Elasticsearch server. This can still be used
for [Native Analytics](docs://analytics/native/README.md) and any custom use cases.

Additionally, this will increase load on your database servers. Depending on your Altis subscription, this may incur extra cost.
