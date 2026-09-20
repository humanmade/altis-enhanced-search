<?php
/**
 * Test Local Server memory configuration.
 *
 * @package altis/enhanced-search
 */

namespace Integration;

use Altis\Enhanced_Search\Local_Server_Extension;
use Altis\Local_Server\Composer\Docker_Compose_Generator;

/**
 * Test Local Server memory configuration.
 */
class LocalServerMemoryTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * The heap can be smaller than the container memory limit.
	 *
	 * @return void
	 */
	public function testSeparateHeapLimit() {
		$service = $this->get_elasticsearch_service( [
			'ES_MEM_LIMIT' => '12g',
			'ES_HEAP_LIMIT' => '8g',
		] );

		$this->assertSame( '12g', $service['mem_limit'] );
		$this->assertContains( 'ES_JAVA_OPTS=-Xms512m -Xmx8g', $service['environment'] );
	}

	/**
	 * Existing configurations continue to use the container limit as the heap limit.
	 *
	 * @return void
	 */
	public function testHeapLimitDefaultsToMemoryLimit() {
		$service = $this->get_elasticsearch_service( [
			'ES_MEM_LIMIT' => '2g',
		] );

		$this->assertSame( '2g', $service['mem_limit'] );
		$this->assertContains( 'ES_JAVA_OPTS=-Xms512m -Xmx2g', $service['environment'] );
	}

	/**
	 * Build the Elasticsearch service configuration.
	 *
	 * @param array $environment Environment values exposed to the extension.
	 * @return array
	 */
	private function get_elasticsearch_service( array $environment ) : array {
		$generator = new Docker_Compose_Generator(
			sys_get_temp_dir(),
			'enhanced-search-test',
			'altis.dev',
			'https://enhanced-search-test.altis.dev/',
			[ 'xdebug' => 'off' ]
		);
		$extension = new class( $environment ) extends Local_Server_Extension {
			/**
			 * Environment values exposed to the extension.
			 *
			 * @var array
			 */
			private $environment;

			/**
			 * Create the test extension.
			 *
			 * @param array $environment Environment values exposed to the extension.
			 */
			public function __construct( array $environment ) {
				$this->environment = $environment;
			}

			/**
			 * Get a test environment value.
			 *
			 * @param string $name Environment variable name.
			 * @param string $default Default value.
			 * @return string
			 */
			protected function get_environment_variable( string $name, string $default ) : string {
				return $this->environment[ $name ] ?? $default;
			}
		};
		$extension->set_config( $generator, [] );

		$config = $extension->filter_compose( [
			'services' => [],
			'volumes' => [],
		] );

		return $config['services']['elasticsearch'];
	}
}
