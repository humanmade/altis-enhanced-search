<?php
/**
 * Search module tests file.
 *
 * @package altis/enhanced-search
 *
 * phpcs:disable WordPress.Files, WordPress.NamingConventions, PSR1.Classes.ClassDeclaration.MissingNamespace, HM.Functions.NamespacedFunctions
 */

/**
 * Search tests.
 */
class ElasticSearchCest {

	/**
	 * Disable cavalcade for all tests.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function _before( AcceptanceTester $I ) {
		$I->bootstrapWith( [ __CLASS__, '_disableCavalcade' ] );
	}

	/**
	 * Term search test.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function testTermSearch( AcceptanceTester $I ) {
		$I->wantToTest( 'Term search works.' );

		$tags = [
			'Alpha',
			'Beta',
			'Theta',
			'Omega',
		];

		$I->loginAsAdmin();
		$I->amOnAdminPage( 'edit-tags.php?taxonomy=post_tag' );
		foreach ( $tags as $tag ) {
			$I->submitForm( '#addtag', [
				'tag-name' => $tag,
			] );
			$I->seeTermInDatabase( [ 'slug' => strtolower( $tag ) ] );
			$I->see( $tag, '.column-primary' );
		}

		$I->submitForm( '#wpbody .search-form', [
			's' => 'alpha',
		] );
		$I->see( 'Alpha', '.column-primary' );

		$I->submitForm( '#wpbody .search-form', [
			's' => 'alp',
		] );
		$I->see( 'Alpha', '.column-primary' );

		$I->submitForm( '#wpbody .search-form', [
			's' => 'thet',
		] );
		$I->see( 'Theta', '.column-primary' );

		$I->submitForm( '#wpbody .search-form', [
			's' => 'theto',
		] );
		$I->see( 'Theta', '.column-primary' );
	}

	/**
	 * Basic synonym search test.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function testSimpleModeSynonymSearch( AcceptanceTester $I ) {
		$I->wantToTest( 'Search synonyms work in simple mode.' );

		$contents = [
			'Cat',
			'Dog',
			'Hamster',
			'Cat Dog Hamster',
		];

		// Create posts.
		foreach ( $contents as $content ) {
			$I->havePostInDatabase( [
				'post_type' => 'post',
				'post_title' => $content,
				'post_content' => "<!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->",
			] );
		}

		// Set up custom user dicts.
		$I->loginAsAdmin();
		$I->amOnAdminPage( 'admin.php?page=search-config' );
		$I->submitForm( '#wpbody form', [
			'synonyms-text' => 'cat, dog, hamster',
		] );

		$this->_index();

		$I->amOnPage( '/?s=cat' );

		foreach ( $contents as $content ) {
			$I->see( $content, '.entry-title' );
		}
	}

	/**
	 * Advanced synonym search test.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function testAdvancedModeSynonymSearch( AcceptanceTester $I ) {
		$I->wantToTest( 'Search synonyms work in advanced mode.' );

		$rollback = $I->bootstrapWith( [ __CLASS__, '_setAdvancedMode' ] );

		$contents = [
			'Sneakers',
			'Trainers',
			'Shoes',
		];

		foreach ( $contents as $content ) {
			$I->havePostInDatabase( [
				'post_type' => 'post',
				'post_title' => $content,
				'post_content' => "<!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->",
			] );
		}

		$I->loginAsAdmin();
		$I->amOnAdminPage( 'admin.php?page=search-config' );
		$I->submitForm( '#wpbody form', [
			'synonyms-text' => "cat, dog, hamster\nsneaker, trainer, shoe, loafer",
		] );

		$this->_index();

		$I->amOnPage( '/?s=loaffer' );

		foreach ( $contents as $content ) {
			$I->see( $content, '.entry-title' );
		}

		$rollback();
	}

	/**
	 * Stopwords search test.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function testStopwords( AcceptanceTester $I ) {
		$I->wantToTest( 'Custom stopwords.' );

		$contents = [
			'Ignore this',
		];

		// Create posts.
		foreach ( $contents as $content ) {
			$I->havePostInDatabase( [
				'post_type' => 'post',
				'post_title' => $content,
				'post_content' => "<!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->",
			] );
		}

		$I->loginAsAdmin();
		$I->amOnAdminPage( 'admin.php?page=search-config' );
		$I->submitForm( '#wpbody form', [
			'stopwords-text' => 'ignore',
		] );

		$this->_index();

		$I->amOnPage( '/?s=ignore' );
		$I->dontSee( 'Ignore this', '.entry-title' );
	}

	/**
	 * User search test.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function testUserSearch( AcceptanceTester $I ) {
		$I->wantToTest( 'User search works correctly.' );

		// Create a user.
		$I->haveUserInDatabase( 'adminsson', 'administrator', [
			'user_email' => 'adam.a@example.com',
			'display_name' => 'Adam Adminsson',
			'meta' => [
				'first_name' => 'Adam',
				'last_name' => 'Adminsson',
				'nickname' => 'addo',
			],
		] );

		$this->_index();

		$I->loginAsAdmin();
		$I->amOnAdminPage( 'users.php' );

		$I->submitForm( '#wpbody form', [
			's' => 'admin',
		] );
		$I->see( 'adminsson', '.column-primary' );

		$I->submitForm( '#wpbody form', [
			's' => 'Adam',
		] );
		$I->see( 'adminsson', '.column-primary' );

		$I->submitForm( '#wpbody form', [
			's' => 'Ada',
		] );
		$I->see( 'adminsson', '.column-primary' );

		$I->submitForm( '#wpbody form', [
			's' => 'example.com',
		] );
		$I->see( 'adminsson', '.column-primary' );
	}

	/**
	 * Custom search results test.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function testCustomSearchResults( AcceptanceTester $I ) {
		$I->wantToTest( 'Custom search results are working.' );

		$contents = [
			'Cat',
			'Shoe',
		];

		// Create posts.
		foreach ( $contents as $content ) {
			$I->havePostInDatabase( [
				'post_type' => 'post',
				'post_title' => $content,
				'post_content' => "<!-- wp:paragraph -->\n<p>{$content}</p>\n<!-- /wp:paragraph -->",
			] );
		}

		$this->_index();

		$I->loginAsAdmin();
		$I->amOnAdminPage( 'post-new.php?post_type=ep-pointer' );
		$I->fillField( [ 'name' => 'post_title' ], 'Cat' );
		$I->waitForElement( '.pointer-search' );
		$I->fillField( '.pointer-search input', 'Shoe' );
		$I->waitForElement( '.pointer-result' );
		$custom_result = $I->grabTextFrom( '.pointer-results .pointer-result:first-child .title' );
		$I->click( '.pointer-results .pointer-result:first-child .add-pointer' );
		$I->waitForElement( '.pointers .pointer-type' );
		$I->waitForElementClickable( '#publish' );
		$I->click( '#publish' );
		$I->wait( 3 );

		$I->amOnPage( '/?s=cat' );
		$I->see( $custom_result, '.entry-title' );
	}

	/**
	 * Reusable block content search test.
	 *
	 * @param AcceptanceTester $I Actor object.
	 * @return void
	 */
	public function testReusableBlockContent( AcceptanceTester $I ) {
		$I->wantToTest( 'Reusable block content is searchable.' );

		$block_id = $I->havePostInDatabase( [
			'post_type' => 'wp_block',
			'post_title' => 'Reusable content!',
			'post_content' => "<!-- wp:paragraph -->\n<p>I am reusable!</p>\n<!-- /wp:paragraph -->",
		] );

		$I->havePostInDatabase( [
			'post_type' => 'post',
			'post_title' => 'I am content!',
			'post_content' => sprintf( '<!-- wp:block {"ref":%d} /-->', $block_id ),
		] );

		$this->_index();

		$I->amOnPage( '/?s=reusable' );
		$I->see( 'I am content!', '.entry-title' );
	}

	/**
	 * Bootstraps the Altis config with advanced search mode.
	 *
	 * @return void
	 */
	public static function _setAdvancedMode() {
		self::_disableCavalcade();
		add_filter( 'altis.config', function ( array $config ) : array {
			$config['modules']['search']['mode'] = 'advanced';
			return $config;
		} );
	}

	/**
	 * Turn cron off for these tests.
	 *
	 * @return void
	 */
	public static function _disableCavalcade() {
		define( 'DISABLE_WP_CRON', true );
		add_filter( 'altis.config', function ( array $config ) : array {
			$config['modules']['cloud']['cavalcade'] = false;
			return $config;
		} );
	}

	/**
	 * Run ElasticPress indexing command.
	 *
	 * Also ensure user dicts are removed.
	 *
	 * @param string $options Additional arguments to append to the indexing command.
	 * @return void
	 */
	protected function _index( string $options = '' ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( sprintf(
			'WPBROWSER_HOST_REQUEST=1 wp elasticpress index --network-wide --setup --yes %s',
			$options
		), $output );
	}

}
