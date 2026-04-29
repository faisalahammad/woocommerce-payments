<?php
/**
 * Class WC_Payments_Admin_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Database_Cache;
use Automattic\Jetpack\Constants;

/**
 * WC_Payments_Admin unit tests.
 */
class WC_Payments_Admin_Test extends WCPAY_UnitTestCase {

	/**
	 * @var WC_Payments_Account|MockObject
	 */
	private $mock_account;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_gateway;

	/**
	 * Mock WC_Payments_API_Client.
	 *
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * Mock Onboarding Service.
	 *
	 * @var WC_Payments_Onboarding_Service|MockObject;
	 */
	private $mock_onboarding_service;

	/**
	 * Mock Order Service.
	 *
	 * @var WC_Payments_Order_Service|MockObject;
	 */
	private $mock_order_service;

	/**
	 * Mock Incentives Service.
	 *
	 * @var WC_Payments_Incentives_Service|MockObject;
	 */
	private $mock_incentives_service;

	/**
	 * Mock Fraud Service.
	 *
	 * @var WC_Payments_Fraud_Service|MockObject;
	 */
	private $mock_fraud_service;

	/**
	 * Mock PM Promotions Service.
	 *
	 * @var WC_Payments_PM_Promotions_Service|MockObject;
	 */
	private $mock_pm_promotions_service;

	/**
	 * Mock database cache.
	 *
	 * @var Database_Cache|MockObject;
	 */
	private $mock_database_cache;

	/**
	 * Backup object of $GLOBALS['current_screen'].
	 *
	 * @var object
	 */
	private $current_screen_backup;

	/**
	 * Order created during notice tests; cleaned up in tear_down_post_kyc_global_state().
	 *
	 * @var int|null
	 */
	private $test_order_id = null;

	/**
	 * @var WC_Payments_Admin
	 */
	private $payments_admin;

	public function set_up() {
		global $menu, $submenu;

		$menu    = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu = null; // phpcs:ignore: WordPress.WP.GlobalVariablesOverride.Prohibited

		// Mock screen.
		$this->current_screen_backup = $GLOBALS['current_screen'] ?? null;
		$GLOBALS['current_screen']   = $this->get_screen_mock(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( ! did_action( 'current_screen' ) ) {
			do_action( 'current_screen', $GLOBALS['current_screen'] ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment
		}

		$this->mock_api_client = $this->getMockBuilder( WC_Payments_API_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_onboarding_service = $this->getMockBuilder( WC_Payments_Onboarding_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_order_service = $this->getMockBuilder( WC_Payments_Order_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_incentives_service = $this->getMockBuilder( WC_Payments_Incentives_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_fraud_service = $this->getMockBuilder( WC_Payments_Fraud_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_pm_promotions_service = $this->getMockBuilder( WC_Payments_PM_Promotions_Service::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_database_cache = $this->getMockBuilder( Database_Cache::class )
			->disableOriginalConstructor()
			->getMock();

		$this->mock_account->method( 'get_capital' )->willReturn(
			[
				'loans'              => [],
				'has_active_loan'    => false,
				'has_previous_loans' => false,
			]
		);

		$this->payments_admin = new WC_Payments_Admin(
			$this->mock_api_client,
			$this->mock_gateway,
			$this->mock_account,
			$this->mock_onboarding_service,
			$this->mock_order_service,
			$this->mock_incentives_service,
			$this->mock_pm_promotions_service,
			$this->mock_fraud_service,
			$this->mock_database_cache
		);
	}

	public function tear_down() {
		// Restore screen backup.
		if ( $this->current_screen_backup ) {
			$GLOBALS['current_screen'] = $this->current_screen_backup; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		parent::tear_down();
	}

	public function test_it_does_not_render_settings_badge(): void {
		global $submenu;

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu['wc-admin&path=/payments/overview'], 0, 2 );
		$settings_item_name = $item_names_by_urls[ WC_Payments_Admin_Settings::get_settings_url() ];

		$this->assertEquals( 'Settings', $settings_item_name );
	}

	public function test_it_does_not_render_payments_badge_if_stripe_is_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/overview'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/connect', $item_names_by_urls );
	}

	public function test_it_renders_payments_badge_if_activation_date_is_older_than_3_days_and_stripe_is_not_connected() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu without submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( false );
		update_option( 'wcpay_activation_timestamp', time() - ( 3 * DAY_IN_SECONDS ) );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments' . WC_Payments_Admin::MENU_NOTIFICATION_BADGE, $item_names_by_urls['wc-admin&path=/payments/connect'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/overview', $item_names_by_urls );
	}

	public function test_it_does_not_render_payments_badge_if_activation_date_is_less_than_3_days() {
		global $menu;
		$this->mock_current_user_is_admin();

		// Make sure we render the menu without submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( false );
		update_option( 'wcpay_menu_badge_hidden', 'no' );
		update_option( 'wcpay_activation_timestamp', time() - ( DAY_IN_SECONDS * 2 ) );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $menu, 0, 2 );
		$this->assertEquals( 'Payments', $item_names_by_urls['wc-admin&path=/payments/connect'] );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/overview', $item_names_by_urls );
	}

	/**
	 * @dataProvider data_rejected_or_under_review_menu
	 */
	public function test_rejected_or_under_review_account_registers_limited_menu( bool $is_rejected, bool $is_under_review ) {
		global $submenu;

		$this->mock_current_user_is_admin();

		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->mock_account->method( 'is_account_rejected' )->willReturn( $is_rejected );
		$this->mock_account->method( 'is_account_under_review' )->willReturn( $is_under_review );

		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );

		// These pages should be registered for rejected/under-review accounts.
		$this->assertArrayHasKey( 'wc-admin&path=/payments/overview', $item_names_by_urls );
		$this->assertArrayHasKey( 'wc-admin&path=/payments/transactions', $item_names_by_urls );
		$this->assertArrayHasKey( 'wc-admin&path=/payments/disputes', $item_names_by_urls );

		// These pages should NOT be registered.
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/deposits', $item_names_by_urls );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/settings/regular', $item_names_by_urls );
		$this->assertArrayNotHasKey( 'wc-admin&path=/payments/documents', $item_names_by_urls );
	}

	public function data_rejected_or_under_review_menu(): array {
		return [
			'rejected account'     => [ true, false ],
			'under review account' => [ false, true ],
		];
	}

	private function mock_current_user_is_admin() {
		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );
	}

	/**
	 * @dataProvider data_maybe_redirect_from_payments_admin_child_pages
	 */
	public function test_maybe_redirect_from_payments_admin_child_pages( $expected_times_redirect_called, $has_working_jetpack_connection, $is_stripe_account_valid, $get_params ) {
		$this->mock_current_user_is_admin();
		$this->payments_admin->add_payments_menu();

		$_GET = $get_params;

		$this->mock_account
			->method( 'has_working_jetpack_connection' )
			->willReturn( $has_working_jetpack_connection );

		$this->mock_account
			->method( 'is_stripe_account_valid' )
			->willReturn( $is_stripe_account_valid );

		$this->mock_account
			->expects( $this->exactly( $expected_times_redirect_called ) )
			->method( 'redirect_to_onboarding_welcome_page' );

		$this->payments_admin->maybe_redirect_from_payments_admin_child_pages();
	}

	/**
	 * Data provider for test_maybe_redirect_from_payments_admin_child_pages
	 */
	public function data_maybe_redirect_from_payments_admin_child_pages() {
		return [
			'no_get_params'        => [
				0,
				false,
				false,
				[],
			],
			'empty_page_param'     => [
				0,
				false,
				false,
				[
					'path' => '/payments/overview',
				],
			],
			'incorrect_page_param' => [
				0,
				false,
				false,
				[
					'page' => 'wc-settings',
					'path' => '/payments/overview',
				],
			],
			'empty_path_param'     => [
				0,
				false,
				false,
				[
					'page' => 'wc-admin',
				],
			],
			'incorrect_path_param' => [
				0,
				false,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/does-not-exist',
				],
			],
			'working Jetpack connection - invalid Stripe account' => [
				1,
				true,
				false,
				[
					'page' => 'wc-admin',
					'path' => '/payments/payouts',
				],
			],
			'not working Jetpack connection - valid Stripe account' => [
				1,
				false,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/payouts',
				],
			],
			'working Jetpack connection - valid Stripe account' => [
				0,
				true,
				true,
				[
					'page' => 'wc-admin',
					'path' => '/payments/transactions',
				],
			],
		];
	}

	/**
	 * Tests WC_Payments_Admin::add_disputes_notification_badge()
	 */
	public function test_disputes_notification_badge_display() {
		global $submenu;

		// Mock the database cache returning a set of disputes.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'needs_response'         => 1,
					'warning_needs_response' => 3,
					'won'                    => 2,
					'lost'                   => 10,
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );
		$dispute_query_args = [
			'page'   => 'wc-admin',
			'path'   => '%2Fpayments%2Fdisputes',
			'filter' => 'awaiting_response',
		];

		$dispute_url = admin_url( add_query_arg( $dispute_query_args, 'admin.php' ) );

		// Assert the submenu includes a disputes item that links directly to the disputes screen with the awaiting_response filter.
		$this->assertArrayHasKey( $dispute_url, $item_names_by_urls );

		// The expected badge content should include 4 disputes needing a response.
		$expected_badge = sprintf( WC_Payments_Admin::UNRESOLVED_NOTIFICATION_BADGE_FORMAT, 4 );

		$this->assertSame( 'Disputes' . $expected_badge, $item_names_by_urls[ $dispute_url ] );
	}

	/**
	 * Tests WC_Payments_Admin::add_disputes_notification_badge()
	 */
	public function test_disputes_notification_badge_no_display() {
		global $submenu;

		// Mock the database cache returning a set of disputes.
		$this->mock_database_cache
			->expects( $this->once() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'won'  => 1,
					'lost' => 3,
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );
		$dispute_menu_item  = $item_names_by_urls['wc-admin&path=/payments/disputes'];

		$this->assertEquals( 'Disputes', $dispute_menu_item );
	}

	/**
	 * Tests WC_Payments_Admin::add_transactions_notification_badge()
	 */
	public function test_transactions_notification_badge_display() {
		global $submenu;

		// Mock the manual capture setting as being enabled.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_option' )
			->with( 'manual_capture' )
			->willReturn( 'yes' );

		// Mock the database cache returning authorizations summary.
		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'count'          => 3,
					'currency'       => 'usd',
					'total'          => 5400,
					'all_currencies' => [
						'eur',
						'usd',
					],
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );

		$transactions_url = 'wc-admin&path=/payments/transactions';

		// Assert the submenu includes a transactions item that links directly to the Transactions screen.
		$this->assertArrayHasKey( $transactions_url, $item_names_by_urls );

		// The expected badge content should include 3 uncaptured transactions.
		$expected_badge = sprintf( WC_Payments_Admin::UNRESOLVED_NOTIFICATION_BADGE_FORMAT, 3 );

		$this->assertSame( 'Transactions' . $expected_badge, $item_names_by_urls[ $transactions_url ] );
	}

	/**
	 * Tests WC_Payments_Admin::add_transactions_notification_badge()
	 */
	public function test_transactions_notification_badge_no_display() {
		global $submenu;

		// Mock the manual capture setting as being enabled.
		$this->mock_gateway
			->expects( $this->once() )
			->method( 'get_option' )
			->with( 'manual_capture' )
			->willReturn( 'yes' );

		// Mock the database cache returning authorizations summary.
		$this->mock_database_cache
			->expects( $this->any() )
			->method( 'get_or_add' )
			->willReturn(
				[
					'count' => 0,
					'total' => 0,
				]
			);

		$this->mock_current_user_is_admin();

		// Make sure we render the menu with submenu items.
		$this->mock_account->method( 'is_stripe_account_valid' )->willReturn( true );
		$this->mock_account->method( 'has_working_jetpack_connection' )->willReturn( true );
		$this->payments_admin->add_payments_menu();

		$item_names_by_urls     = wp_list_pluck( $submenu[ WC_Payments_Admin::PAYMENTS_SUBMENU_SLUG ], 0, 2 );
		$transactions_menu_item = $item_names_by_urls['wc-admin&path=/payments/transactions'];

		$this->assertSame( 'Transactions', $transactions_menu_item );
	}

	public function test_enqueue_wc_payment_settings_spotlight_does_not_enqueue_on_wrong_page() {
		global $wp_scripts, $wp_styles;

		// Arrange.
		$wp_scripts = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_styles  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$_GET['page'] = 'wc-payments';
		$_GET['tab']  = 'products'; // Wrong WC settings tab.

		// Mock the current screen.
		$GLOBALS['current_screen']->id = 'woocommerce_page_wc-settings';

		// Mock the WooCommerce version to be at the minimum required version.
		Constants::set_constant( 'WC_VERSION', '9.9.2' );

		// Act.
		$this->payments_admin->enqueue_wc_payment_settings_spotlight();

		// Assert.
		$this->assertFalse( wp_script_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );

		// Clean up.
		unset( $_GET['page'], $_GET['tab'] );
		Constants::clear_constants();
	}

	public function test_enqueue_wc_payment_settings_spotlight_does_not_enqueue_on_old_wc_version() {
		global $wp_scripts, $wp_styles;

		// Arrange.
		$wp_scripts = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_styles  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$_GET['page'] = 'wc-payments';
		$_GET['tab']  = 'checkout';

		// Mock the current screen.
		$GLOBALS['current_screen']->id = 'woocommerce_page_wc-settings';

		// Mock the WooCommerce version to NOT be at the minimum required version.
		Constants::set_constant( 'WC_VERSION', '9.9.1' );

		// Act.
		$this->payments_admin->enqueue_wc_payment_settings_spotlight();

		// Assert.
		$this->assertFalse( wp_script_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );
		$this->assertFalse( wp_style_is( 'WCPAY_WC_PAYMENTS_SETTINGS_SPOTLIGHT', 'enqueued' ) );

		// Clean up.
		unset( $_GET['page'], $_GET['tab'] );
		Constants::clear_constants();
	}

	/**
	 * Data provider for test_should_show_review_prompt.
	 *
	 * @return array
	 */
	public function provider_should_show_review_prompt() {
		return [
			'should not show on section page'            => [
				'page_setup'  => [
					'page'    => 'wc-settings',
					'tab'     => 'checkout',
					'section' => 'woocommerce_payments',
				],
				'is_eligible' => true,
				'dismissed'   => 0,
				'maybe_later' => 0,
				'expected'    => false,
			],
			'should not show when account not eligible'  => [
				'page_setup'  => [
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				],
				'is_eligible' => false,
				'dismissed'   => 0,
				'maybe_later' => 0,
				'expected'    => false,
			],
			'should not show when permanently dismissed' => [
				'page_setup'  => [
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				],
				'is_eligible' => true,
				'dismissed'   => time(),
				'maybe_later' => 0,
				'expected'    => false,
			],
			'should not show when in cooldown'           => [
				'page_setup'  => [
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				],
				'is_eligible' => true,
				'dismissed'   => 0,
				'maybe_later' => time() - ( 5 * DAY_IN_SECONDS ), // 5 days ago.
				'expected'    => false,
			],
			'should show when cooldown expired'          => [
				'page_setup'  => [
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				],
				'is_eligible' => true,
				'dismissed'   => 0,
				'maybe_later' => time() - ( 11 * DAY_IN_SECONDS ), // 11 days ago.
				'expected'    => true,
			],
			'should show when all conditions pass'       => [
				'page_setup'  => [
					'page' => 'wc-settings',
					'tab'  => 'checkout',
				],
				'is_eligible' => true,
				'dismissed'   => 0,
				'maybe_later' => 0,
				'expected'    => true,
			],
		];
	}

	/**
	 * Test should_show_review_prompt method with various scenarios.
	 *
	 * @dataProvider provider_should_show_review_prompt
	 *
	 * @param array $page_setup   Page setup parameters.
	 * @param bool  $is_eligible  Whether account is eligible.
	 * @param int   $dismissed    Timestamp when dismissed (0 if not dismissed).
	 * @param int   $maybe_later  Timestamp when maybe later clicked (0 if not).
	 * @param bool  $expected     Expected return value.
	 */
	public function test_should_show_review_prompt( $page_setup, $is_eligible, $dismissed, $maybe_later, $expected ) {
		// Arrange: Set up page.
		foreach ( $page_setup as $key => $value ) {
			$_REQUEST[ $key ] = $value;
		}

		// Mock the current screen.
		$GLOBALS['current_screen']->id = 'woocommerce_page_wc-settings';

		// Mock account eligibility.
		$this->mock_account->method( 'is_review_prompt_eligible' )->willReturn( $is_eligible );

		// Mock current user and set user meta.
		$user_id = 1;
		wp_set_current_user( $user_id );

		if ( $dismissed > 0 ) {
			update_user_meta( $user_id, 'woocommerce_admin_wc_payments_review_prompt_dismissed', $dismissed );
		}

		if ( $maybe_later > 0 ) {
			update_user_meta( $user_id, 'woocommerce_admin_wc_payments_review_prompt_maybe_later', $maybe_later );
		}

		// Act.
		$result = $this->payments_admin->should_show_review_prompt();

		// Assert.
		$this->assertSame( $expected, $result );

		// Clean up.
		foreach ( array_keys( $page_setup ) as $key ) {
			unset( $_REQUEST[ $key ] );
		}
		delete_user_meta( $user_id, 'woocommerce_admin_wc_payments_review_prompt_dismissed' );
		delete_user_meta( $user_id, 'woocommerce_admin_wc_payments_review_prompt_maybe_later' );
	}

	/**
	 * Returns an object mocking what we need from \WP_Screen.
	 *
	 * @return object
	 */
	private function get_screen_mock(): object {
		$screen_mock = $this->getMockBuilder( \stdClass::class )->setMethods( [ 'in_admin', 'add_option' ] )->getMock();
		$screen_mock->method( 'in_admin' )->willReturn( true );
		foreach ( [ 'id', 'base', 'action', 'post_type' ] as $key ) {
			$screen_mock->{$key} = '';
		}

		return $screen_mock;
	}

	// -------------------------------------------------------------------------
	// Post-KYC activation notice helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a WC_Payments_Admin instance wired for post-KYC notice tests.
	 */
	private function make_admin_for_post_kyc_test(
		bool $is_connected = true,
		bool $is_account_valid = true,
		bool $is_test_drive = false,
		bool $payments_enabled = true
	): WC_Payments_Admin {
		$mock_gateway = $this->getMockBuilder( WC_Payment_Gateway_WCPay::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_gateway->method( 'is_connected' )->willReturn( $is_connected );

		$mock_account = $this->getMockBuilder( WC_Payments_Account::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_account->method( 'is_stripe_account_valid' )->willReturn( $is_account_valid );
		$mock_account->method( 'get_account_status_data' )->willReturn(
			[
				'testDrive'       => $is_test_drive,
				'paymentsEnabled' => $payments_enabled,
			]
		);
		$mock_account->method( 'get_capital' )->willReturn(
			[
				'loans'              => [],
				'has_active_loan'    => false,
				'has_previous_loans' => false,
			]
		);

		return new WC_Payments_Admin(
			$this->mock_api_client,
			$mock_gateway,
			$mock_account,
			$this->mock_onboarding_service,
			$this->mock_order_service,
			$this->mock_incentives_service,
			$this->mock_pm_promotions_service,
			$this->mock_fraud_service,
			$this->mock_database_cache
		);
	}

	/**
	 * Sets global state for post-KYC notice eligibility tests.
	 *
	 * @param int  $days_since_kyc Days to subtract from now when setting the KYC completion date.
	 * @param bool $has_orders     Whether to create a WooPayments order.
	 */
	private function set_up_post_kyc_global_state( int $days_since_kyc = 8, bool $has_orders = false ): void {
		delete_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT );
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - $days_since_kyc * DAY_IN_SECONDS );

		WC_Payments::mode()->live();

		$admin_user = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user );

		if ( $has_orders ) {
			$order = wc_create_order();
			$order->set_payment_method( 'woocommerce_payments' );
			$order->set_status( 'completed' );
			$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::PRODUCTION );
			$order->save();
			$this->test_order_id = $order->get_id();
		}
	}

	private function tear_down_post_kyc_global_state(): void {
		WC_Payments::mode()->live();
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		delete_transient( WC_Payments_Account::POST_KYC_ACTIVATION_ELIGIBLE_TRANSIENT );

		foreach ( [ 7, 14, 30 ] as $stage ) {
			delete_user_meta( get_current_user_id(), WC_Payments_Admin::USER_META_POST_KYC_ACTIVATION_DISMISSED_PREFIX . $stage );
			delete_user_meta( get_current_user_id(), WC_Payments_Admin::USER_META_POST_KYC_ACTIVATION_DISMISSED_PREFIX . $stage . '_shown' );
		}

		if ( null !== $this->test_order_id ) {
			$order = wc_get_order( $this->test_order_id );
			if ( $order ) {
				$order->delete( true );
			}
			$this->test_order_id = null;
		}
	}

	// -------------------------------------------------------------------------
	// get_post_kyc_activation_stage tests
	// -------------------------------------------------------------------------

	public function test_get_post_kyc_activation_stage_returns_null_when_no_date(): void {
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertNull( $admin->get_post_kyc_activation_stage() );
	}

	public function test_get_post_kyc_activation_stage_returns_null_before_day_7(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 3 * DAY_IN_SECONDS );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertNull( $admin->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	public function test_get_post_kyc_activation_stage_returns_7_between_day_7_and_13(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 7 * DAY_IN_SECONDS );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertSame( 7, $admin->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	public function test_get_post_kyc_activation_stage_returns_14_between_day_14_and_29(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 14 * DAY_IN_SECONDS );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertSame( 14, $admin->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	public function test_get_post_kyc_activation_stage_returns_30_at_and_after_day_30(): void {
		update_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION, time() - 45 * DAY_IN_SECONDS );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertSame( 30, $admin->get_post_kyc_activation_stage() );

		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
	}

	// -------------------------------------------------------------------------
	// should_show_post_kyc_activation_notice tests
	// -------------------------------------------------------------------------

	public function test_should_show_post_kyc_activation_notice_returns_true_when_all_conditions_met(): void {
		$this->set_up_post_kyc_global_state();
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertTrue( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_no_kyc_date(): void {
		$this->set_up_post_kyc_global_state();
		delete_option( WC_Payments_Account::KYC_COMPLETION_DATE_OPTION );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_before_day_7(): void {
		$this->set_up_post_kyc_global_state( 3 );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_stage_dismissed(): void {
		$this->set_up_post_kyc_global_state();
		update_user_meta( get_current_user_id(), WC_Payments_Admin::USER_META_POST_KYC_ACTIVATION_DISMISSED_PREFIX . 7, time() );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_user_lacks_capability(): void {
		$this->set_up_post_kyc_global_state();
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_not_connected(): void {
		$this->set_up_post_kyc_global_state();
		$admin = $this->make_admin_for_post_kyc_test( false );

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_for_test_drive(): void {
		$this->set_up_post_kyc_global_state();
		$admin = $this->make_admin_for_post_kyc_test( true, true, true );

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_payments_not_enabled(): void {
		$this->set_up_post_kyc_global_state();
		$admin = $this->make_admin_for_post_kyc_test( true, true, false, false );

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_in_test_mode(): void {
		$this->set_up_post_kyc_global_state();
		WC_Payments::mode()->test();
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_false_when_merchant_has_orders(): void {
		$this->set_up_post_kyc_global_state( 8, true );
		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertFalse( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_should_show_post_kyc_activation_notice_returns_true_when_merchant_only_has_test_orders(): void {
		$this->set_up_post_kyc_global_state();

		$order = wc_create_order();
		$order->set_payment_method( 'woocommerce_payments' );
		$order->set_status( 'completed' );
		$order->update_meta_data( WC_Payments_Order_Service::WCPAY_MODE_META_KEY, \WCPay\Constants\Order_Mode::TEST );
		$order->save();
		$this->test_order_id = $order->get_id();

		$admin = $this->make_admin_for_post_kyc_test();

		$this->assertTrue( $admin->should_show_post_kyc_activation_notice() );

		$this->tear_down_post_kyc_global_state();
	}

	// -------------------------------------------------------------------------
	// hide_post_kyc_activation_notice tests
	// -------------------------------------------------------------------------

	public function test_hide_post_kyc_activation_notice_sets_dismissed_meta_and_tracks_event(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = wp_create_nonce( 'wcpay_hide_post_kyc_activation_notice_nonce' );

		$admin              = $this->make_admin_for_post_kyc_test();
		$redirect_intercept = function () {
			throw new \Exception( 'redirect' );
		};
		add_filter( 'wp_redirect', $redirect_intercept );
		try {
			$admin->hide_post_kyc_activation_notice();
		} catch ( \Exception $e ) {
			$this->assertSame( 'redirect', $e->getMessage() );
		}
		remove_filter( 'wp_redirect', $redirect_intercept );

		$dismissed = get_user_meta( get_current_user_id(), WC_Payments_Admin::USER_META_POST_KYC_ACTIVATION_DISMISSED_PREFIX . 7, true );
		$this->assertNotEmpty( $dismissed );

		$events = \WCPay\Tracker::get_admin_events();
		$this->assertArrayHasKey( 'wcpay_post_kyc_activation_notice_dismissed', $events );
		$this->assertSame( 7, $events['wcpay_post_kyc_activation_notice_dismissed']['stage'] );

		\WCPay\Tracker::remove_admin_event( 'wcpay_post_kyc_activation_notice_dismissed' );
		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'] );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_hide_post_kyc_activation_notice_ignores_missing_params(): void {
		$admin = $this->make_admin_for_post_kyc_test();
		unset( $_GET['wcpay-hide-post-kyc-activation-notice'] );

		$admin->hide_post_kyc_activation_notice();

		$this->assertEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin::USER_META_POST_KYC_ACTIVATION_DISMISSED_PREFIX . 7, true ) );
	}

	public function test_hide_post_kyc_activation_notice_ignores_invalid_nonce(): void {
		$this->set_up_post_kyc_global_state();

		$_GET['wcpay-hide-post-kyc-activation-notice']   = '1';
		$_GET['_wcpay_post_kyc_activation_notice_nonce'] = 'bad-nonce';

		$admin = $this->make_admin_for_post_kyc_test();
		$admin->hide_post_kyc_activation_notice();

		$this->assertEmpty( get_user_meta( get_current_user_id(), WC_Payments_Admin::USER_META_POST_KYC_ACTIVATION_DISMISSED_PREFIX . 7, true ) );

		unset( $_GET['wcpay-hide-post-kyc-activation-notice'], $_GET['_wcpay_post_kyc_activation_notice_nonce'] );
		$this->tear_down_post_kyc_global_state();
	}

	// -------------------------------------------------------------------------
	// maybe_show_post_kyc_activation_notice tests
	// -------------------------------------------------------------------------

	public function test_maybe_show_post_kyc_activation_notice_outputs_container_div(): void {
		$this->set_up_post_kyc_global_state();
		$admin = $this->make_admin_for_post_kyc_test();

		ob_start();
		$admin->maybe_show_post_kyc_activation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="wcpay-post-kyc-activation-notice">', $output );

		$this->tear_down_post_kyc_global_state();
	}

	public function test_maybe_show_post_kyc_activation_notice_tracks_impression_once_per_stage(): void {
		$this->set_up_post_kyc_global_state();
		$admin = $this->make_admin_for_post_kyc_test();

		ob_start();
		$admin->maybe_show_post_kyc_activation_notice();
		$admin->maybe_show_post_kyc_activation_notice();
		ob_end_clean();

		$events = \WCPay\Tracker::get_admin_events();
		$this->assertArrayHasKey( 'wcpay_post_kyc_activation_notice_shown', $events );
		$this->assertSame( 7, $events['wcpay_post_kyc_activation_notice_shown']['stage'] );

		\WCPay\Tracker::remove_admin_event( 'wcpay_post_kyc_activation_notice_shown' );
		$this->tear_down_post_kyc_global_state();
	}

	// -------------------------------------------------------------------------
	// init_hooks — post-KYC notice hook registration
	// -------------------------------------------------------------------------

	public function test_init_hooks_registers_post_kyc_sections_hook_for_active_tab(): void {
		$_GET['page'] = 'wc-settings';
		$_GET['tab']  = 'checkout';
		$admin        = $this->make_admin_for_post_kyc_test();

		$admin->init_hooks();

		$this->assertNotFalse(
			has_action( 'woocommerce_sections_checkout', [ $admin, 'maybe_show_post_kyc_activation_notice' ] )
		);

		remove_action( 'woocommerce_sections_checkout', [ $admin, 'maybe_show_post_kyc_activation_notice' ] );
		unset( $_GET['page'], $_GET['tab'] );
	}
}
