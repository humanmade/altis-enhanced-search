# Reindexing Content

The search index is kept in sync with CMS content whenever content is created, updated or deleted via the CMS. There are some situations such as direct MySQL database alterations and code changes that will mean the search index has to be repopulated.

When making changes the data or analyzers that are stored in the search index, you must re-index the content so all results and search operations are using the new data specification. Re-indexing is done via CLI commands, as they are potentially long running, high memory usage tasks.

The `elasticpress` CLI command is used for all index management operations.

To re-sychronize the index for all sites

```sh
wp elasticpress index --network-wide
```

Re-sychronise the index and updating mappings (needed when updating synonym definitions)

```sh
wp elasticpress index --setup
```

## CLI Recommendations

When using CLI to perform re-indexes, it's recommended to set the `--posts-per-page` argument to a figure around `200`. By default, this figure is set to `350` which will often cause service timeouts. For example:

```sh
wp elasticpress index --posts-per-page 200
```

See `wp help elasticpress` for all available CLI commands.
