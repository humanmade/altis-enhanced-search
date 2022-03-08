# Setup and Reindexing

Altis automatically indexes your content as it changes to keep search indexes in sync with the original data stored in the database. However, some manual steps may need to be performed.


## Setup

Before data can be indexed, the index "mapping" must be created. This works like a table definition in MySQL, containing a list of fields, their data types, and any field settings. The index mappings can be created by running `wp elasticpress put-mapping`.


## Verifying Mappings

To verify one or all Elasticsearch mappings are as expected, you can use the `wp elasticpress get-mapping` subcommand. The command will print all mappings as a JSON string, which is in line with how index data is provided.

By passing an optional index name, the response includes mappings for that index only:

```shell
wp elasticpress get-mapping --index-name=ep-mysitealtisdev-post-1
```

To format this in a human-readable way, you may want to pipe the output to a script that allows for pretty-printing JSON, for example, like so:

- `wp elasticpress get-mapping | jq .` (see [`jq`](https://stedolan.github.io/jq/))
- `wp elasticpress get-mapping | json` (see [`json`](https://trentm.com/json/))

This also works when executing the WP-CLI command within [Local Server](docs://local-server/) from your host:

```shell
composer server cli -- elasticpress get-mapping | jq .
```


## Reindexing

Occasionally, a "reindex" may be required. This can occur if data is out of sync in Elasticsearch, if data is updated directly in the database, or when conducting imports.

When making changes the data or analyzers that are stored in the search index, you must re-index the content so all results and search operations are using the new data specification.

Re-indexing is done via CLI commands, as they are potentially long running, high memory usage tasks. To reindex a site, run:

```sh
wp elasticpress index
```

**Note:** During the indexing process, the site will not use the Elasticsearch index for searches/queries, so some features like faceting or autosuggest will not work as expected until the process is finished.


## Recreating Mappings

If your index mapping changes, the mapping will need to be recreated. This may be needed after an Altis upgrade, when adjusting index settings via filters, or changing language settings.

To re-sychronise the index and update mappings, run:

```sh
wp elasticpress index --setup
```

To run across the entire network, use `xargs` as above. You'll also need to create the "network alias", which is a special index which allows cross-site searching:

```sh
wp site list --field=url | xargs -I % wp elasticpress index --url=% --setup && wp elasticpress recreate-network-alias
```

**Note:** Changing the mapping requires deleting the index and recreating it, which will remove all existing documents from your index. Performing this process may take significant time.


## CLI Recommendations

### Performing across a Network

When performing these tasks across your whole network, we recommend against using the `--network-wide` flag provided by ElasticPress. When using the `--network-wide` flag on sufficiently large networks, the CLI process may run out of memory and be killed by the operating system. 

To better manage memory, it is recommended to use an `xargs` pipeline to process each site. `wp site list --field=url` will print each site URL on a new line, which can then be ingested by `xargs` to be processed individually.

To perform a reindex across all sites:

```sh
wp site list --field=url | xargs -I % wp elasticpress index --url=%
```

To recreate mappings across the entire network:

```sh
wp site list --field=url | xargs -I % wp elasticpress index --url=% --setup && wp elasticpress recreate-network-alias
```

This also recreates the "network alias", which is a special index which allows cross-site searching.


### Limiting Page Size

When using CLI to perform re-indexes, it's recommended to set the `--per-page` argument to a figure around `200`. By default, this figure is set to `350` which will often cause service timeouts. For example:

```sh
wp site list --field=url | xargs -I % wp elasticpress index --url=% --per-page=200
```

See `wp help elasticpress` for all available CLI commands.
