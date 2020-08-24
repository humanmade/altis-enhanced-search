# Reindexing Content

The search index is kept in sync with CMS content whenever content is created, updated or deleted via the CMS. There are some situations such as direct MySQL database alterations and code changes that will mean the search index has to be repopulated.

When making changes the data or analyzers that are stored in the search index, you must re-index the content so all results and search operations are using the new data specification. Re-indexing is done via CLI commands, as they are potentially long running, high memory usage tasks.

The `elasticpress` CLI command is used for all index management operations.

As these tasks are long-running commands, it is recommended to run the index on individual sites instead of using the `--network-wide` flag. When using the `--network-wide` flag on sufficiently large networks, the CLI process would run out of memory and be killed by the operating system. To better manage memory, it is recommended to use an `xargs` pipeline to process each site. To re-sychronize the index for all sites:

```sh
wp site list --field=url | xargs -I % wp elasticpress index --url=%
```

`wp site list --field=url` will print each site URL on a new line and ingested by `xargs` to be processed individually.

To re-sychronise the index and update mappings (needed when updating synonym definitions):

```sh
wp site list --field=url | xargs -I % wp elasticpress index --url=% --setup
```

## CLI Recommendations

When using CLI to perform re-indexes, it's recommended to set the `--posts-per-page` argument to a figure around `200`. By default, this figure is set to `350` which will often cause service timeouts. For example:

```sh
wp site list --field=url | xargs -I % wp elasticpress index --url=% --posts-per-page 200
```

See `wp help elasticpress` for all available CLI commands.
