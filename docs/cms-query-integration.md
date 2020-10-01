# CMS Query Integration

The Search module overrides the default search functionality to query the specialized search index in place of a standard MySQL query. This means all default search operations using the CMS search APIs for posts, users and terms such as `WP_Query`, `WP_User_Query`, `WP_Term_Query` and the REST API search endpoints will transparently make use of the search index by default.

It is possible to control this behaviour and thus use the search index for non-search queries. The reason for doing this is to improve performance when running complex queries, such as meta queries, that would otherwise be too slow to do in the database.

Each query class accepts an array of parameters. ElasticPress checks for the value of an `ep_integrate` parameter. If this is true, the query is performed by Elasticsearch instead of MySQL. If the `s` (search) parameter is present `ep_integrate` will be set to `true` automatically.

The following example sets `ep_integrate` to true for a non-search query where the `s` parameter is not used.

```php
$posts = new WP_Query( [
	'ep_integrate' => true, // Use Elasticsearch.
	'post_type' => 'project',
	'meta_query' => [
		[
			'key' => 'color',
			'value' => [ 'blue', 'green' ],
			'compare' => 'IN'
		],
	],
] );
```

Note that in the above example you would need a PHP Code Sniffer exception for using the meta query to pass [Automated Code Review](docs://guides/code-review/README.md).

You can also circumvent the use of Elasticsearch for searches by explicitly setting `ep_integrate` to false in the query arguments or by using the `pre_get_posts` action and checking for certain conditions:

```php
add_action( 'pre_get_posts', function ( WP_Query $query ) {
	// Avoid using Elasticsearch for the 'project' post type.
	if ( ! $query->is_search() || $query->get( 'post_type' ) !== 'project' ) {
		return;
	}

	$query->set( 'ep_integrate', false );
}, 20 );
```

## Autosuggest
Because of the default way that Elasticsearch analyses text it performs better with complete search terms, as you might expect to be submitted from a search form.

In some cases you might want to provide a dynamic interface where results are fetched as the user types. The standard method of search will not produce good results until a full word has been typed out, so Altis provides a means of analysing and fetching results based on partial search terms.

In the same way as `ep_integrate` can be passed to any query class, you can pass the parameter `autosuggest` along with the `s` parameter:

```php
$posts = new WP_Query( [
	'autosuggest' => true,
	// A partial search term.
	// Setting `s` will cause `ep_integrate` to be added and set to true.
	's' => 'str',
] );
```

The `autosuggest` parameter defaults to true if the `DOING_AJAX` constant is defined and `true`.

### Filters

By default only a subset of fields are analysed for autosuggest searches, but this can be filtered to add additional fields for each indexable object type.

**`altis.search.autosuggest_post_fields : array`** defaults to `post_title`.

**`altis.search.autosuggest_term_fields : array`** defaults to `name`.

**`altis.search.autosuggest_user_fields : array`** defaults to `user_nicename`, `display_name` and `user_login`.

**Note:** if the above filters are used the site content will need to be re-indexed for autosuggestions to work properly.
