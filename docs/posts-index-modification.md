# Search Index Modification

## Storing Additional Data

The default search index will include all post data, such as content, author, title, date, meta and terms. There are situations where you may want to add custom data, for example: suppose you want to support search for content based on a custom relationship to another post.

In this case, you would modify the data for a all posts before the data is sent to the search index for storage. To do so, add your extra data via the `ep_post_sync_args_post_prepare_meta` filter.

```php
add_filter( 'ep_post_sync_args_post_prepare_meta', function ( array $post_data, int $post_id ) : array {

	// This filter is called for all post types, so only add data to our "events" post type.
	if ( $post_data['post_type'] !== 'events' ) {
		return $post_data;
	}

	// Get the country name from the country ID stored in post meta.
	$country = event_get_country( get_post_meta( 'country_id', $post_id, true ) );
	$post_data['country'] = $country->get_name();

	return $post_data;
}, 10, 2 );
```

This will mean the searchable data stored in the index will include the country name.

## Searching Additional Data

By default, when using the CMS search APIs such as `WP_Query` only the default search fields will be used, not any additional data stored using the above method. To include posts matching the `country` field, use the `ep_search_fields` hook to include your custom field.

```php
add_filter( 'ep_search_fields', function ( array $fields ) : array {
	$fields[] = 'country';
	return $fields;
} );
```
