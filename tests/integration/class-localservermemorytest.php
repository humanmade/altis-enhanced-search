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
	 * The heap uses 50% of the container memory limit.
	 *
	 * @return void
	 */
	public function testHeapUsesHalfOfMemoryLimit() {
		$service = $this->get_elasticsearch_service( [
			'ES_MEM_LIMIT' => '16g',
		] );

		$this->assertSame( '16g', $service['mem_limit'] );
		$this->assertContains( 'ES_JAVA_OPTS=-Xms8192m -Xmx8192m', $service['environment'] );
	}

	/**
	 * The default container limit provides a 512 MB heap.
	 *
	 * @return void
	 */
	public function testDefaultMemoryLimitProvides512MbHeap() {
		$service = $this->get_elasticsearch_service( [] );

		$this->assertSame( '1g', $service['mem_limit'] );
		$this->assertContains( 'ES_JAVA_OPTS=-Xms512m -Xmx512m', $service['environment'] );
	}

	/**
	 * Docker Compose byte units are converted to JVM megabytes.
	 *
	 * @dataProvider provideMemoryLimits
	 *
	 * @param string $memory_limit Container memory limit.
	 * @param string $heap_limit Expected JVM heap limit.
	 * @return void
	 */
	public function testDockerComposeByteUnits( string $memory_limit, string $heap_limit ) {
		$service = $this->get_elasticsearch_service( [
			'ES_MEM_LIMIT' => $memory_limit,
		] );

		$this->assertContains( "ES_JAVA_OPTS=-Xms{$heap_limit} -Xmx{$heap_limit}", $service['environment'] );
	}

	/**
	 * Memory limits and their expected heap limits.
	 *
	 * @return array
	 */
	public function provideMemoryLimits() : array {
		return [
			'bytes' => [ '2147483648b', '1024m' ],
			'kilobytes' => [ '2097152kb', '1024m' ],
			'megabytes' => [ '2048m', '1024m' ],
			'gigabytes' => [ '2gb', '1024m' ],
			'uppercase' => [ '2G', '1024m' ],
		];
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
