# Search Configuration

Depending on your use case and requirements there are many ways you may wish to configure the search engine. The following built in configuration options aim to provide some flexibility out of the box however more advanced customisations may require some development work and deep knowledge of Elasticsearch.

## Search Mode
The mode setting determines whether or not the search will allow the use of advanced search syntax. The default mode is "simple" search. To enable advanced mode you would use the configuration below.

```json
{
	"extra": {
		"altis": {
			"modules": {
				"search": {
					"mode": "advanced"
				}
			}
		}
	}
}
```

By setting `mode` to "advanced" it is possible to write more complex search terms using operators:

- Quoted strings will force an exact match
- Brackets `(` and `)` denote precedence of terms
- `AND` or a plus `+` character will match both the terms
- `OR` or a pipe `|` character will match either of the terms
- A word followed by `*` will match anything with that prefix
- A word preceded by `-` will be excluded from the results

For example the following search would return results with exact matches for "the law", or the words "judge" and "dred" and excluding the word "marvel".

```
"the law" OR (judge AND dredd) -marvel
```

## Strict Search
By default searching will match _all_ the provided search terms.

This means the more specific a search query is the fewer results will be provided. For example "the quick brown fox" will be interpreted as "the `AND` quick `AND` brown `AND` fox".

Setting `strict` to `false` will change the behaviour to match each individual word as well as the full search query, with complete matches being scored more highly.

```json
{
	"extra": {
		"altis": {
			"modules": {
				"search": {
					"strict": false
				}
			}
		}
	}
}
```

Strict matching is recommended if you have a user interface that allows sorting search results by anything other than relevance such as date.

## Field Boosting
By default the post title, excerpt, content, author name and taxonomy terms are searched against. Field boosting allows you to modify the importance of those fields with regards to search results. The following configuration will increase the important of the post title, excerpt and a custom meta field above the default value of 1.

```json
{
	"extra": {
		"altis": {
			"modules": {
				"search": {
					"field-boost": {
						"post_title": 3,
						"post_excerpt": 2,
						"meta.custom_field": 2
					}
				}
			}
		}
	}
}
```

It is important to note that the field names should match the fields in the elasticsearch index and do not always correspond 1:1 with database fields.
