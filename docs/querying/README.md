# Querying

The querying process is how Altis finds and fetches data from your Elasticsearch indexes.


## Automatic CMS Integration

Altis [integrates with queries in the CMS by default](cms-integration.md), transparently sending search queries to Elasticsearch while leaving other queries unaffected.


## Autosuggest

Altis also provides [support for Ajax-based autosuggestions](autosuggest.md) which automatically integrate with search forms on your site.


## Custom Queries

For advanced usage, you can perform [custom queries against the Elasticsearch indexes](custom-queries.md).

**Note:** Altis cannot provide support for custom queries directly against Elasticsearch.


## Using Google Analytics (GA)

Google Analytics can be configured to ingest search queries for further analysis and insights. This will inform future weightings and search configuration modifications. When configuring GA, the default query parameter value is `s`, see [Google Analytics - Set up Site Search](https://support.google.com/analytics/answer/1012264) for more information.
