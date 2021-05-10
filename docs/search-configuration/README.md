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
						"meta.custom_field.value": 2
					}
				}
			}
		}
	}
}
```

It is important to note that the field names should match the fields in the elasticsearch index and do not always correspond 1:1 with database fields.

## Fuzzy Matching
By default some degree of fuzzy matching is allowed so that simple spelling errors can still return some results, for example "breif" would match "brief". This can sometimes result in unwanted search results however with short words and acronyms.

Fuzzy matching works by providing an _edit distance_ as an integer from 0-2. The number indicates how many edits are allowed for a term to match. Edits can be one of the following:

* Changing a character (box → fox)
* Removing a character (click → lick)
* Inserting a character (sic → sick)
* Transposing two adjacent characters (act → cat)

**Note:** when using "advanced" search mode quoted strings will not use fuzzy matching.

**Note:** if you have synonyms configured they will not use fuzzy matching.

Below is the default fuzzy search configuration.

```json
{
	"extra": {
		"altis": {
			"modules": {
				"search": {
					"fuzziness": {
						"distance": "auto:4,7",
						"prefix-length": 1,
						"max-expansions": 40,
						"transpositions": true
					}
				}
			}
		}
	}
}
```

**`distance`**

This is the [Elasticsearch fuzziness value](https://www.elastic.co/guide/en/elasticsearch/reference/6.3/common-options.html#fuzziness) and determines the maximum edit distance for fuzzy matching. This value can also be set as the value for `fuzziness` directly if you do not need to edit the other options.

- If an integer is provided this is used as a fixed edit distance eg. 0, 1 or 2 edits allowed
- If a string is provided it must be in the form `auto:[min],[max]`
  - If `min` is 3 all terms from 0-2 characters long will have an edit distance of 0
  - If `max` is 6 all terms from `min` to 6 characters long will have an edit distance of 1
  - Any terms longer than `max` will have an edit distance of 2

**`prefix-length`**

This value determines how many characters at the start of a search term must match before fuzzy matching is applied. For instance a prefix length of 1 will mean that "lotion" will match "lotoin" but not "potion" or "motion".

**`max-expansions`**

This value is the number of fuzzy terms generated from a search term when used for matching. Higher values will result in slower searches but more matches and lower values will result in faster searches but fewer matches.

If you have a prefix length of 0 you may wish to increase this value to get more matches.

**`transpositions`**

This option allows you to prevent transpositions from being counted as a single edit. In the above example the transposition "act" to "cat" has an edit distance of 1. Setting this value to `false` would mean the same transposition would have an edit distance of 2 because 2 letters have been replaced.

## Max Query Length

Typically search queries can be as long as the user wishes. As a preventative measure against slow queries and search request spamming, Altis limits search query strings to **80 characters** by default.

You can configure this value in 2 ways.

Using the Altis Search config option `max-query-length`:

```json
{
	"extra": {
		"altis": {
			"modules": {
				"search": {
					"max-query-length": 100
				}
			}
		}
	}
}
```

Using the filter `altis.search.max_query_length`:

```php
add_filter( 'altis.search.max_query_length', function () {
	return 100;
} );
```

covid+vaccines+caused+more+deaths+than+all+other+vaccines+combined
