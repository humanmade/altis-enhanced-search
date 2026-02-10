# Elasticsearch Specifications

Altis Cloud environments include Elasticsearch servers which are used by the Search module. An equivalent setup is also available
with [Local Server](docs://local-server/)

## Supported Versions

Altis supports Elasticsearch version 7.10

We recommended using the most up-to-date version wherever possible, as new versions come with significant performance improvements
as well as additional capabilities.

You can [request an Elasticsearch upgrade](docs://guides/updating-elasticsearch/) to switch to a specific version.

## Supported Elasticsearch Plugins

The following Elasticsearch plugins are available on Cloud and Local environments:

- ICU Analysis
- Ingest Attachment Processor
- Ingest User Agent Processor
- Japanese (Kuromoji) Analysis
- Mapper Murmur3
- Mapper Size
- Phonetic Analysis
- Smart Chinese Analysis
- Stempel Polish Analysis
- Ukrainian Analysis
- Seunjeon Korean Analysis

## Storage, CPU, and Memory

Just like other components of your Altis Cloud environments, the Altis team automatically manages storage, CPU, and memory for
Elasticsearch on your behalf.

If you have specific higher requirements, contact Altis Support to discuss increasing your provisioned environment. Please note that
additional charges may apply for usage exceeding that of similarly-situated customers.
