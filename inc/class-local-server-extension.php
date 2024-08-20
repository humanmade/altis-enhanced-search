<?php
/**
 * Local Server Docker Compose file generator.
 */

namespace Altis\Enhanced_Search;

use Altis;
use Altis\Local_Server\Composer\Compose_Extension;
use Altis\Local_Server\Composer\Docker_Compose_Generator;

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
	 * @param array $config Full docker-compose.yml configuration.
	 * @return array Altered docker-compose.yml configuration.
	 */
	public function filter_compose( array $config ) : array {
		// Skip entirely if the module is disabled.
		$full_config = Altis\get_config()['modules'];
		if ( ! ( $full_config['search']['enabled'] ?? true ) ) {
			return $config;
		}

		// Handle the main ES service.
		$config['volumes']['es-data'] = null;
		$config['services'] = array_merge( $config['services'], $this->get_service_elasticsearch() );

		foreach ( [ 'php', 'cavalcade' ] as $php_svc ) {
			if ( empty( $config['services'][ $php_svc ] ) ) {
				continue;
			}

			$config['services'][ $php_svc ]['external_links'][] = "proxy:elasticsearch-{$this->generator->hostname}";
			$config['services'][ $php_svc ]['environment']['ELASTICSEARCH_HOST'] = 'elasticsearch';
			$config['services'][ $php_svc ]['environment']['ELASTICSEARCH_PORT'] = 9200;
			$config['services'][ $php_svc ]['depends_on']['elasticsearch'] = [
				'condition' => 'service_healthy',
			];
		}

		if ( $full_config['search']['kibana'] || $full_config['local-server']['kibana'] ) {
			if ( $full_config['local-server']['kibana'] ) {
				trigger_error(
					'extra.altis.modules.local-server.kibana is deprecated, use extra.altis.modules.search.kibana instead.',
					E_USER_DEPRECATED
				);
			}

			$config['services'] = array_merge( $config['services'], $this->get_service_kibana() );
		}

		return $config;
	}

	/**
	 * Get the Elasticsearch service.
	 *
	 * @return array
	 */
	protected function get_service_elasticsearch() : array {
		$mem_limit = getenv( 'ES_MEM_LIMIT' ) ?: '1g';

		$version_map = [
			'7.10' => 'humanmade/altis-local-server-elasticsearch:4.1.0',
			'7' => 'humanmade/altis-local-server-elasticsearch:4.1.0',
			'6.8' => 'humanmade/altis-local-server-elasticsearch:3.1.0',
			'6' => 'humanmade/altis-local-server-elasticsearch:3.1.0',
			'6.3' => 'humanmade/altis-local-server-elasticsearch:3.0.0',
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
					'traefik.port=9200',
					'traefik.protocol=http',
					'traefik.docker.network=proxy',
					"traefik.frontend.rule=HostRegexp:elasticsearch-{$this->generator->hostname}",
					"traefik.domain=elasticsearch-{$this->generator->hostname}",
				],
				'environment' => [
					'http.max_content_length=10mb',
					// Force ES into single-node mode (otherwise defaults to zen discovery as
					// network.host is set in the default config).
					'discovery.type=single-node',
					// Use max container memory limit as the max JVM heap allocation value.
					"ES_JAVA_OPTS=-Xms512m -Xmx{$mem_limit}",
				],
			],
		];
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
			'6.8' => 'blacktop/kibana:6.8',
			'6' => 'blacktop/kibana:6.8',
			'6.3' => 'blacktop/kibana:6.3',
		];

		$this->check_elasticsearch_version( array_keys( $version_map ) );

		$image = $version_map[ $this->get_elasticsearch_version() ];

		$yml_file = 'kibana.yml';
		if ( version_compare( $this->get_elasticsearch_version(), '7', '>=' ) ) {
			$yml_file = 'kibana-7.yml';
		}

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
				'labels' => [
					'traefik.port=5601',
					'traefik.protocol=http',
					'traefik.docker.network=proxy',
					"traefik.frontend.rule=Host:{$this->generator->hostname};PathPrefix:/kibana",
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
		if ( ! empty( $full_config['modules']['search']['version'] ) ) {
			return (string) $full_config['modules']['search']['version'];
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
