# Related Posts

To find related posts leveraging Elasticsearch, use the `RelatedPosts::find_related()` function.

The `find_related()` function is used to find related posts based on the content of a given post.

The function requires a single parameter (`$post_id`) with another optional parameter (`$return`). The `$post_id` will be used
to find the posts that are related to it, with `$return` specifying the number of related posts to return, which defaults to 5.

This function is a registered feature of `ElasticPress` and is invoked as follows:

```php
\ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id, 3 )
```

If an out-of-the-box solution is desired, the "ElasticPress - Related Posts" block can be added to your site's sidebar. In
order for the widget to work correctly it needs to be added to the sidebar which will be displayed for a single post.

