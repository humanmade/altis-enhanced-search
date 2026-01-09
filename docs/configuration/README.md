# Search Configuration

Altis strives to provide a great out-of-the-box search experience for most uses.

For advanced usage, additional configuration is available to adjust search behaviour in indexing and querying.

## Tuning

When searching, results can be ordered by relevancy score. This score is based on a blend of various factors, including index
configuration, field configuration, the query being run, and the indexed data.

To help adjust relevancy, Altis provides [various configuration settings to tune relevancy and search behaviour](./tuning.md),
allowing for stricter or more permissive matching, advanced query operators, and fuzzy matching.

## Date Decay

For most use cases, you'll want to treat newer results as more relevant than older ones. Altis
includes [date decay functionality](./date-decay.md) which automatically adjusts relevancy scores based on dates. These parameters
can be tuned as part of the configuration.

## User Dictionaries

When indexing content, Elasticsearch uses analyzers to convert text from a raw string into data which can be queried. Altis
provides [analyzers for common languages](../language-support.md) out of the box.

Altis also provides the ability to upload [custom user dictionaries](./custom-dictionaries.md), which allow adding synonyms, stop
words and custom text analysis for Japanese to improve analysis.

## Additional Configuration Options

The following options can be enabled/disabled via the search configuration.

- `"related-posts": true|false (default)`
- `"facets": true|false|['match-type' => "all" (default)|...]`
- `"woocommerce": true|false (default)`
- `"autosuggest": true|false (default)` - See [autosuggest](../querying/autosuggest.md)
- `"users": true (default)|false`
- `"terms": true (default)|false`
- `"comments": true|false (default)`

```json
{
    "extra": {
        "altis": {
            "modules": {
                "search": {
                    "related": false,
                    "facets": {
                        "match-type": "all"
                    },
                    "woocommerce": false,
                    "autosuggest": false,
                    "users": true,
                    "terms": true
                }
            }
        }
    }
}
```


### Facets

Facets are a feature in ElasticPress which add control to filter content by one or more taxonomies. A widget can be added so when
viewing a content list (archive), the taxonomy and all of its terms will be displayed. This will allow a visitor to further filter
content.

Depending on the configuration specified for `facets`, if the `match-type` property is set to `any`, it will force the results to
match any selected taxonomy term. If set to `all`, it will match to results with all of the selected terms.


## Local Server

See [the Local Server documentation](docs://local-server/elasticsearch.md) for information on configuring Local Server for ES settings.
