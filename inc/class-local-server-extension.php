<?php
/**
 * Local Server Docker Compose file generator.
 */

namespace Altis\Enhanced_Search;

use Altis;
use Altis\Local_Server\Composer\Compose_Extension;
use Altis\Local_Server\Composer\Docker_Compose_Generator;
use InvalidArgumentException;

/**
 * This class adds the Elasticsearch and Kibana services to the Local Server docker stack.
 */
class Local_Server_Extension implements Compose_Extension {
	protected Docker_Compose_Generator $generator;

	/**
	 * Configure the extension.
	 *
	 * @param Docker_Compose_Generator $generator The root generator.
	 * @param array $args An optional array of arguments to modify the behaviour of the generator.
	 */
	public function set_config( Docker_Compose_Generator $generator, array $args ) : void {
		$this->generator = $generator;
	}

	/**
	 * Filter the docker-compose.yml config.
	 *
	 * @param array $docker_config Full docker-compose.yml configuration.
	 *
	 * @return array Altered docker-compose.yml configuration.
	 */
	public function filter_compose( array $docker_config ) : array {
		// Skip entirely if the module is disabled.
		$full_config = Altis\get_config()['modules'] ?? [];
		if ( ! ( $full_config['search']['enabled'] ?? true ) ) {
			return $docker_config;
		}

		$local_config = $full_config['search']['local'] ?? [];
		if ( ! ( $local_config['enabled'] ?? true ) ) {
			return $docker_config;
		}

		// Handle the main ES service.
		$docker_config['volumes']['es-data'] = null;
		$docker_config['services'] = array_merge( $docker_config['services'], $this->get_service_elasticsearch() );

		foreach ( [ 'php', 'cavalcade' ] as $php_svc ) {
			if ( empty( $docker_config['services'][ $php_svc ] ) ) {
				continue;
			}

			$docker_config['services'][ $php_svc ]['external_links'][] = "proxy:elasticsearch-{$this->generator->hostname}";
			$docker_config['services'][ $php_svc ]['environment']['ELASTICSEARCH_HOST'] = 'elasticsearch';
			$docker_config['services'][ $php_svc ]['environment']['ELASTICSEARCH_PORT'] = 9200;
			$docker_config['services'][ $php_svc ]['depends_on']['elasticsearch'] = [
				'condition' => 'service_healthy',
			];
		}

		// Enable Kibana. (Defaults to true, but supports new + old style).
		$has_kibana = $local_config['kibana'] ?? $full_config['local-server']['kibana'] ?? true;
		if ( $has_kibana ) {
			if ( ! empty( $full_config['local-server']['kibana'] ) ) {
				trigger_error(
					'extra.altis.modules.local-server.kibana is deprecated, use extra.altis.modules.search.kibana instead.',
					E_USER_DEPRECATED
				);
			}

			$docker_config['services'] = array_merge( $docker_config['services'], $this->get_service_kibana() );
		}

		return $docker_config;
	}

	/**
	 * Get the Elasticsearch service.
	 *
	 * @return array
	 */
	protected function get_service_elasticsearch() : array {
		$mem_limit = $this->get_environment_variable( 'ES_MEM_LIMIT', '1g' );
		$heap_limit = $this->get_elasticsearch_heap_limit( $mem_limit );

		$version_map = [
			'7.10' => 'humanmade/altis-local-server-elasticsearch:4.1.0',
			'7' => 'humanmade/altis-local-server-elasticsearch:4.1.0',
		];

		$this->check_elasticsearch_version( array_keys( $version_map ) );

		$image = $version_map[ $this->get_elasticsearch_version() ];

		return [
			'elasticsearch' => [
				'image' => $image,
				'restart' => 'unless-stopped',
				'container_name' => "{$this->generator->project_name}-es",
				'ulimits' => [
					'memlock' => [
						'soft' => -1,
						'hard' => -1,
					],
				],
				'mem_limit' => $mem_limit,
				'volumes' => [
					'es-data:/usr/share/elasticsearch/data',
					"{$this->generator->root_dir}/content/uploads/es-packages:/usr/share/elasticsearch/config/packages",
				],
				'ports' => [
					'9200',
				],
				'networks' => [
					'proxy',
					'default',
				],
				'healthcheck' => [
					'test' => [
						'CMD-SHELL',
						'curl --silent --fail localhost:9200/_cluster/health || exit 1',
					],
					'interval' => '5s',
					'timeout' => '5s',
					'retries' => 25,
				],
				'labels' => [
					'traefik.enable=true',
					'traefik.docker.network=proxy',
					"traefik.http.routers.{$this->generator->project_name}-elasticsearch.rule=Host(`elasticsearch-{$this->generator->hostname}`)",
					"traefik.http.routers.{$this->generator->project_name}-elasticsearch.entrypoints=web,websecure",
					"traefik.http.routers.{$this->generator->project_name}-elasticsearch.service={$this->generator->project_name}-elasticsearch",
					"traefik.http.services.{$this->generator->project_name}-elasticsearch.loadbalancer.server.port=9200",
				],
				'environment' => [
					'http.max_content_length=10mb',
					// Force ES into single-node mode (otherwise defaults to zen discovery as
					// network.host is set in the default config).
					'discovery.type=single-node',
					// Limit the heap to 50% so Elasticsearch has memory for native processes and the filesystem cache.
					// See https://www.elastic.co/guide/en/elasticsearch/reference/current/jvm-options.html#set-jvm-heap-size.
					"ES_JAVA_OPTS=-Xms{$heap_limit} -Xmx{$heap_limit}",
				],
			],
		];
	}

	/**
	 * Calculate the Elasticsearch JVM heap limit from the container memory limit.
	 *
	 * @param string $memory_limit Docker Compose byte value.
	 * @return string
	 */
	protected function get_elasticsearch_heap_limit( string $memory_limit ) : string {
		if ( ! preg_match( '/^(\d+)(b|k|kb|m|mb|g|gb)$/i', $memory_limit, $matches ) ) {
			throw new InvalidArgumentException( 'ES_MEM_LIMIT must be a Docker Compose byte value, for example 1g or 2048m.' );
		}

		$unit_multipliers = [
			'b' => 1,
			'k' => 1024,
			'kb' => 1024,
			'm' => 1024 ** 2,
			'mb' => 1024 ** 2,
			'g' => 1024 ** 3,
			'gb' => 1024 ** 3,
		];
		$memory_bytes = (int) $matches[1] * $unit_multipliers[ strtolower( $matches[2] ) ];
		$heap_megabytes = intdiv( $memory_bytes, 2 * ( 1024 ** 2 ) );

		if ( $heap_megabytes < 1 ) {
			throw new InvalidArgumentException( 'ES_MEM_LIMIT must provide at least 2 MB of container memory.' );
		}

		return "{$heap_megabytes}m";
	}

	/**
	 * Get an environment variable with a fallback value.
	 *
	 * @param string $name Environment variable name.
	 * @param string $default Default value.
	 * @return string
	 */
	protected function get_environment_variable( string $name, string $default ) : string {
		return getenv( $name ) ?: $default;
	}

	/**
	 * Get the Kibana service.
	 *
	 * @return array
	 */
	protected function get_service_kibana() : array {
		$version_map = [
			'7.10' => 'humanmade/altis-local-server-kibana:1.1.1',
			'7' => 'humanmade/altis-local-server-kibana:1.1.1',
		];

		$this->check_elasticsearch_version( array_keys( $version_map ) );

		$image = $version_map[ $this->get_elasticsearch_version() ];

		$yml_file = 'kibana.yml';
		if ( version_compare( $this->get_elasticsearch_version(), '7', '>=' ) ) {
			$yml_file = 'kibana-7.yml';
		}

		$mem_limit = getenv( 'LS_MEM_LIMIT' ) ?: '256M';

		return [
			'kibana' => [
				'image' => $image,
				'container_name' => "{$this->generator->project_name}-kibana",
				'networks' => [
					'proxy',
					'default',
				],
				'ports' => [
					'5601',
				],
				'mem_limit' => $mem_limit,
				'labels' => [
					'traefik.enable=true',
					'traefik.docker.network=proxy',
					"traefik.http.routers.{$this->generator->project_name}-kibana.rule=(Host(`{$this->generator->hostname}`) || Host(`*.{$this->generator->hostname}`)) && PathPrefix(`/kibana`)",
					"traefik.http.routers.{$this->generator->project_name}-kibana.entrypoints=web,websecure",
					"traefik.http.routers.{$this->generator->project_name}-kibana.service={$this->generator->project_name}-kibana",
					"traefik.http.services.{$this->generator->project_name}-kibana.loadbalancer.server.port=5601",
				],
				'depends_on' => [
					'elasticsearch' => [
						'condition' => 'service_healthy',
					],
				],
				'volumes' => [
					"{$this->generator->config_dir}/{$yml_file}:/usr/share/kibana/config/kibana.yml",
				],
			],
		];
	}

	/**
	 * Get the configured Elasticsearch version.
	 *
	 * @return int
	 */
	protected function get_elasticsearch_version() : string {
		$full_config = Altis\get_config();

		// Try new config first.
		if ( ! empty( $full_config['modules']['search']['local']['version'] ) ) {
			return (string) $full_config['modules']['search']['local']['version'];
		}

		// Try legacy, and warn if it's still used.
		if ( ! empty( $full_config['modules']['local-server']['elasticsearch'] ) ) {
			trigger_error(
				'extra.altis.modules.local-server.elasticsearch is deprecated, use extra.altis.modules.search.version instead.',
				E_USER_DEPRECATED
			);
			return (string) $full_config['modules']['local-server']['elasticsearch'];
		}

		return '7';
	}

	/**
	 * Check the configured Elasticsearch version in config.
	 *
	 * @param array $versions List of available version numbers.
	 * @return void
	 */
	protected function check_elasticsearch_version( array $versions ) {
		$versions = array_map( 'strval', $versions );
		rsort( $versions );
		if ( in_array( $this->get_elasticsearch_version(), $versions, true ) ) {
			return;
		}

		echo sprintf(
			"The configured elasticsearch version \"%s\" is not supported.\nTry one of the following:\n  - %s\n",
			// phpcs:ignore HM.Security.EscapeOutput.OutputNotEscaped
			$this->get_elasticsearch_version(),
			// phpcs:ignore HM.Security.EscapeOutput.OutputNotEscaped
			implode( "\n  - ", $versions )
		);
		exit( 1 );
	}
}
