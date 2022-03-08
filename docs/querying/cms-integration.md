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

## Customising Searched Fields

Altis will search key content fields by default, including those defined in the [field boosting config](./search-configuration/README.md#field-boosting), depending on the type of content.

### Using Query Parameters

`WP_Query`, `WP_Term_Query` and `WP_User_Query` support a `search_fields` parameter. This can be used to override the default searched fields and even to modify how the fields are boosted using the `<field>^<boost value>` syntax.

```php
$posts = new WP_Query( [
	'ep_integrate' => true, // Use Elasticsearch.
	'post_type' => 'project',
	'search_fields' => [
		'post_title',
		'meta.client.value^2',
	],
] );
```

When using the `search_fields` parameter, the `ep_search_fields` filter will be run which can modify the list of searched fields.

### Using Filters

As noted above the `ep_search_fields` filter can be used to further customise queries that use the `search_fields` parameter:

```php
add_filter( 'ep_search_fields', function ( array $fields ) : array {
	$fields[] = 'alt';
	$fields[] = 'meta.keywords.value';
	return $fields;
} );
```

To modify the default searchable fields (when the `search_fields` parameter is _not_ used) you need to use the `ep_weighting_default_post_type_weights` filter. The filter has 2 parameters, a keyed array of fields including their enabled status and weight, and the post type.

```php
add_filter( 'ep_weighting_default_post_type_weights', function ( array $fields, string $post_type ) : array {
	// Add fields for all post types.
	$fields['meta.keywords.value'] = [
		'enabled' => true,
		'weight' => 1.0,
	];

	// Add fields for specific post types.
	switch ( $post_type ) {
		case 'attachment':
			$fields['alt'] = [
				'enabled' => true,
				'weight' => 1.5,
			];
			break;
		case 'project':
			$fields['meta.client.value'] = [
				'enabled' => true,
				'weight' => 1.0,
			];
			break;
	}

	return $fields;
}, 10, 2 );
```
