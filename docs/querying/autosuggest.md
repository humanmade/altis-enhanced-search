# Autosuggest

The Search module provides autosuggest functionality, which automatically integrates with search forms on the website to show a dropdown list of suggestions as users type.

(This feature is provided by ElasticPress, with additions from Altis.)


## How autosuggest works

Autosuggest works by finding search forms on your site and automatically integrating with these.

Using the element selector specified within the search configuration, ElasticPress JS listens for user input on search forms. As the user types, an Ajax request is sent to the backend to query for possible results.

[Just like with other queries](cms-query-integration.md), ElasticPress automatically generates an Elasticsearch query based on the WordPress query being run.

Unlike regular searches, autosuggestion operates on a fuzzy basis, as it needs to match against partial inputs as the user types. ElasticPress automatically converts certain fields in the search into fuzzy search fields for this reason. Additionally, as the query is run based on user input from the frontend, restrictions are applied to ensure only public information is made available.


## Specifying autosuggest behaviour

In the same way as `ep_integrate` can be passed to any query class, you can pass the parameter `autosuggest` along with the `s` parameter (or `search` for user and term queries):

```php
$posts = new WP_Query( [
	'autosuggest' => true,
	// A partial search term.
	// Setting `s` will cause `ep_integrate` to be added and set to true.
	's' => 'str',
] );
```

The `autosuggest` parameter defaults to true in the following circumstances:

- If the `DOING_AJAX` constant is defined and `true`, to allow for search as you type functionality
- Media library searches
- User searches
- Term searches


## Modifying autosuggest behaviour

### Element selector

By default, ElasticPress will listen on `.ep-autosuggest`, `input[type="search"]`, and `.search-field` elements. This will match the built-in search widget and search form block.

In some cases, this can interfere with other inputs on your site. This behaviour can be changed to match your theme by filtering the `ep_autosuggest_default_selectors` filter:

```php
add_filter( 'ep_autosuggest_default_selectors', function ( string $selectors ) {
	return '.my-search-input, input[type="search"].autosuggest';
} );
```


### Altering search fields

By default, autosuggest will search against fields in the same way as a [regular search query](cms-query-integration.md). In addition, fuzziness is applied to the fields using an ngram model to account for partial inputs.

By default only a subset of fields are analysed for autosuggest searches, but this can be filtered to add additional fields for each indexable object type.

**`altis.search.autosuggest_post_fields : array`** defaults to `post_title`.

**`altis.search.autosuggest_term_fields : array`** defaults to `name`.

**`altis.search.autosuggest_user_fields : array`** defaults to `user_nicename`, `display_name`, `user_login`, `meta.first_name.value`, `meta.last_name.value` and `meta.nickname.value`.

**Note:** if the above filters are used the site content will need to be re-indexed for autosuggestions to work properly.


### Modifying the query

Autosuggest queries can be modified using the low-level `altis.search.autosuggest_query` filter, which provides the full Elasticsearch query.

For example, to exclude any posts with post meta of `_hide` set to `1`:

```php
add_filter( 'altis.search.autosuggest_query', function ( array $query ) : array {
	$query['post_filter']['bool']['must_not'] = [
		[ 'term' => [ 'post.meta._hide.raw' => '1' ] ]
	];
	return $query;
} );
```

As autosuggest queries are sent from the client side, the `post_filter` part of the query is hardcoded to only allow publicly searchable posts with the status `publish` to be returned.

To alter this behaviour, you can manually override the field via the `altis.search.autosuggest_query` filter:

```php
add_filter( 'ep_term_suggest_post_status', function ( array $query ) : array {
	$query['post_filter']['bool']['must'][0]['term']['post_status'] = [
		'publish',
		'my_custom_status',
	];
	return $query;
} );
```
