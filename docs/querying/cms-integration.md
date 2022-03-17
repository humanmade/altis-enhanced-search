# CMS Integration

The Search module automatically integrates with the search functionality in the CMS, transparently upgrading requests to use Elasticsearch where possible.

(This feature is provided by ElasticPress, with additions from Altis.)


## Querying with ElasticPress

By default, all search operations using the query APIs will transparently make use of the search index. This includes `WP_Query`, `WP_User_Query`, `WP_Term_Query`, and REST API queries, **where the search parameter is specified** (typically `s`).

You may want to manually enable this behaviour to use the search index for non-search queries. This may improve performance when running complex queries, such as meta queries, that would otherwise be too slow to do in the database.

**Note:** This usage is advanced, and may have wide-ranging implications for your queries, as you're querying the indexed fields rather than the original, source fields. Ensure you test this behaviour extensively.

To enable the use of ElasticPress integration, set the `ep_integrate` query param to `true`. (If the `s` (search) parameter is present `ep_integrate` will be set to `true` automatically.)

For example, the following query finds projects based on meta value, but does not contain search parameters. By default, ElasticPress would not be used, but is enabled explicitly by setting `ep_integrate` to `true`.

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

**Note:** This example may trigger [Automated Code Review](docs://guides/code-review/README.md) warnings due to the use of a meta query. While meta queries have a lower performance impact with Elasticsearch, it's important to test performance characteristics.

The automatic integration can be disabled on a per query basis by explicitly setting `ep_integrate` to false in the query arguments or on the `pre_get_posts` action. For example:

```php
add_action( 'pre_get_posts', function ( WP_Query $query ) {
	// Avoid using Elasticsearch for the 'project' post type.
	if ( $query->is_search() && $query->get( 'post_type' ) === 'project' ) {
		$query->set( 'ep_integrate', false );;
	}
} );
```


## Customising Searched Fields

Altis will search key content fields by default, including those defined in the [field boosting config](../search-configuration/README.md#field-boosting), depending on the type of content.


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
