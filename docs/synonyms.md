# Synonyms

For better search relevance and results, a dictionary synonyms can be very useful. For example, you may want search results for the phrase "Checking Accounts" to match content that is titled "Current Accounts".

Synonyms must be added to the Elasticsearch mapping at index time, it is not possible to add synonym dictionaries at runtime without re-indexing all content and recreating mappings. Synonyms must be added to the Elasticsearch index as a `filter` detailed in [Using Synonyms](https://www.elastic.co/guide/en/elasticsearch/guide/current/using-synonyms.html).

To create the Elasticsearch synonym filter, use the `ep_config_mapping` filter to modify the index mapping before it is created.

```php
add_filter( 'ep_config_mapping', function ( array $mapping ) : array {

	// Add all the synonyms in a new filter.
	$mapping['settings']['analysis']['filter']['add_synonyms'] = [
		'type' => 'synonym',
		'synonyms' => [
			'current,checking,currentaccount',
		],
	];

	// Specify the "add_synomyms" filter in the mapping analyzer.
	$mapping['settings']['analysis']['analyzer']['default']['filter'][] = 'add_synonyms';

	return $mapping;
} );
```
