# Using Elasticsearch

When working with custom data types or advanced search implementations, you may want to build direct integrations with Elasticsearch, rather than using the higher level features and APIs provided for CMS content.

## Making HTTP Requests

Elasticsearch uses the HTTP protocol for all requests and tasks. You can use the built-in HTTP API to make these requests, or you can use a HTTP client library of your choosing.

Use the `Altis\Enhanced_Search\get_elasticsearch_url()` function to retrieve the Elastcisearch cluster endpoint when making your own requests.

Any requests made to Elasticsearch using the built-in HTTP API functions (`wp_remote_get`, `wp_remote_post`, `wp_remote_request`, etc) will automatically sign requests for the appropriate authentication HTTP headers.

For example, to create an index for a custom data type:

```php
$request = wp_remote_request(
	Altis\Enhanced_Search\get_elasticsearch_url() . '/tweets',
	[
		'method' => 'PUT',
		'headers' => [
			'Content-Type' => 'application/json',
		],
		'body'   => json_encode( [
			'mappings' => [
				'_doc' => [
					'properties' => [
						'content' => [ 'type' => 'text' ],
					],
				],
			],
		] ),
	]
);

var_dump( json_decode( wp_remote_retrieve_body( $request ) ) );
```

## PSR7 HTTP Requests

To sign your own PSR7 requests (if using Guzzle for example) with authentication headers, use the `Altis\Enhanced_Search\sign_psr7_request()` function:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

$client = new Client();
$request = new Request( 'GET', Altis\Enhanced_Search\get_elasticsearch_url() . '/_cluster/health' );
$signed_request = Altis\Enhanced_Search\sign_psr7_request( $request );

$response = $client->send( $signed_request );
```
