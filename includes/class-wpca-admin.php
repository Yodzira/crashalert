<?php
/**
 * Admin UI: CrashAlert top-level menu (timeline + settings), admin-bar
 * status badge, AJAX endpoints for the Telegram/webhook test button and
 * history clearing. Capability: manage_options everywhere.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_wpca_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'admin_post_wpca_clear', array( __CLASS__, 'handle_clear' ) );
		add_action( 'admin_post_wpca_settings', array( __CLASS__, 'handle_settings' ) );
	}

	public static function menu() {
		add_menu_page(
			'CrashAlert',
			'CrashAlert' . self::menu_bubble(),
			'manage_options',
			'crashalert',
			array( __CLASS__, 'render_timeline' ),
			'dashicons-shield',
			3
		);
		add_submenu_page(
			'crashalert',
			__( 'Event timeline', 'crashalert' ),
			__( 'Event timeline', 'crashalert' ),
			'manage_options',
			'crashalert',
			array( __CLASS__, 'render_timeline' )
		);
		add_submenu_page(
			'crashalert',
			__( 'Settings', 'crashalert' ),
			__( 'Settings', 'crashalert' ),
			'manage_options',
			'crashalert-settings',
			array( __CLASS__, 'render_settings' )
		);
	}

	private static function menu_bubble() {
		$recent = WPCA_Repository::count();
		if ( ! $recent ) {
			return '';
		}
		$last_seen = 0;
		foreach ( WPCA_Repository::get_events( 1 ) as $row ) {
			$last_seen = (int) $row['last_seen'];
		}
		// Red bubble only for fresh events (48 h), otherwise silent history.
		return $last_seen >= time() - 2 * DAY_IN_SECONDS
			? ' <span class="awaiting-mod count-1"><span class="plugin-count">' . (int) $recent . '</span></span>'
			: '';
	}

	public static function admin_bar( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$events = WPCA_Repository::get_events( 1 );
		if ( $events ) {
			$title = '🔴 ' . sprintf( 'Сбой %s', wp_date( 'd.m H:i', (int) $events[0]['last_seen'] ) );
		} else {
			$title = '🟢 ' . __( 'All clear', 'crashalert' );
		}
		$bar->add_node(
			array(
				'id'    => 'wpca-status',
				'title' => esc_html( $title ),
				'href'  => admin_url( 'admin.php?page=crashalert' ),
			)
		);
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'crashalert' ) ) {
			return;
		}
		wp_enqueue_style( 'wpca-admin', plugins_url( 'assets/css/admin.css', WPCA_FILE ), array(), WPCA_VERSION );
		wp_enqueue_script( 'wpca-admin', plugins_url( 'assets/js/admin.js', WPCA_FILE ), array( 'jquery' ), WPCA_VERSION, true );
		wp_localize_script(
			'wpca-admin',
			'wpcaI18n',
			array(
				'testing'    => __( 'Sending test…', 'crashalert' ),
				'ok'         => __( 'Delivered — check the channel.', 'crashalert' ),
				'failed'     => __( 'Failed. Check token/chat (Telegram) or URL.', 'crashalert' ),
				'error'      => __( 'Request error.', 'crashalert' ),
			)
		);
	}

	/** AJAX: send a test alert through the requested channel. */
	public static function ajax_test() {
		check_ajax_referer( 'wpca_test', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'forbidden' ), 403 );
		}
		$channel = isset( $_POST['channel'] ) ? sanitize_key( $_POST['channel'] ) : '';
		$error   = WPCA_Alerter::send_test( $channel );
		if ( $error ) {
			wp_send_json_error( array( 'msg' => $error ) );
		}
		wp_send_json_success();
	}

	/** POST: clear history (with nonce). */
	public static function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'crashalert' ) );
		}
		check_admin_referer( 'wpca_clear' );
		WPCA_Repository::erase_all();
		wp_safe_redirect( admin_url( 'admin.php?page=crashalert&cleared=1' ) );
		exit;
	}

	/** POST: save settings (with nonce). */
	public static function handle_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'crashalert' ) );
		}
		check_admin_referer( 'wpca_settings' );
		$clean = WPCA_Settings::sanitize( isset( $_POST['wpca'] ) ? wp_unslash( $_POST['wpca'] ) : array() );
		update_option( WPCA_Settings::KEY, $clean );
		wp_safe_redirect( admin_url( 'admin.php?page=crashalert-settings&saved=1' ) );
		exit;
	}

	public static function render_timeline() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
		$per    = 20;
		$filter = isset( $_GET['culprit'] ) ? sanitize_key( $_GET['culprit'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$rows   = WPCA_Repository::get_events( $per, ( $paged - 1 ) * $per, $filter );
		$total  = WPCA_Repository::count( $filter );
		$down   = get_option( 'wpcrash_down' );
		?>
		<div class="wrap">
			<h1>CrashAlert</h1>

			<?php if ( is_array( $down ) && ! empty( $down['since'] ) ) : ?>
				<div class="notice notice-error"><p>
					<strong>🔴 <?php esc_html_e( 'Site is currently in a crashed state.', 'crashalert' ); ?></strong>
					<?php echo esc_html( sprintf( 'Since %s. Culprit: %s', wp_date( 'd.m.Y H:i', (int) $down['since'] ), $down['culprit'] ) ); ?>
				</p></div>
			<?php else : ?>
				<div class="notice notice-success"><p><strong>🟢 <?php esc_html_e( 'Site is up — no crash in progress.', 'crashalert' ); ?></strong>
				<?php
				$total_events = WPCA_Repository::count();
				if ( $total_events ) {
					echo esc_html( sprintf( 'Events in history: %d.', $total_events ) );
				}
				?>
				</p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'History cleared.', 'crashalert' ); ?></p></div>
			<?php endif; ?>

			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpca_clear' ), 'wpca_clear' ) ); ?>">
					<?php esc_html_e( 'Clear history', 'crashalert' ); ?>
				</a>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=crashalert-settings' ) ); ?>">
					<?php esc_html_e( 'Settings / Telegram wizard', 'crashalert' ); ?>
				</a>
			</p>

			<p class="description">
				<?php esc_html_e( 'Pro: PHP deprecation radar, unlimited alert channels and a weekly health report.', 'crashalert' ); ?>
				<a href="https://yodsira.duckdns.org/buy/crashalert" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to CrashAlert Pro', 'crashalert' ); ?> &rarr;</a>
			</p>

			<h2><?php esc_html_e( 'Event timeline', 'crashalert' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'When', 'crashalert' ); ?></th>
					<th><?php esc_html_e( 'Human explanation', 'crashalert' ); ?></th>
					<th><?php esc_html_e( 'Culprit', 'crashalert' ); ?></th>
					<th><?php esc_html_e( 'Error', 'crashalert' ); ?></th>
					<th>×</th>
				</tr></thead>
				<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No events recorded yet. Silence is good.', 'crashalert' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) :
					$explain = WPCA_Explainer::explain( $row['message'] );
					?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd.m.Y H:i', (int) $row['last_seen'] ) ); ?></td>
						<td>
							<strong><?php echo esc_html( $explain['title'] ); ?></strong><br>
							<span class="description"><?php echo esc_html( $explain['hint'] ); ?></span>
						</td>
						<td><?php echo esc_html( $row['culprit_kind'] . ( $row['culprit_name'] ? ': ' . $row['culprit_name'] : '' ) ); ?></td>
						<td><code><?php echo esc_html( mb_substr( $row['message'], 0, 120 ) ); ?></code><br>
							<span class="description"><?php echo esc_html( $row['file'] . ':' . $row['line'] ); ?></span></td>
						<td><?php echo (int) $row['occurrences'] > 1 ? '×' . (int) $row['occurrences'] : ''; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$pages = (int) ceil( $total / $per );
			if ( $pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo wp_kses_post( paginate_links( array( 'total' => $pages, 'current' => $paged ) ) );
				echo '</div></div>';
			}
			?>
		</div>
		<?php
	}

	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = WPCA_Settings::all();
		$masked = $s['telegram_token'] ? '••••' . substr( $s['telegram_token'], -4 ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CrashAlert settings', 'crashalert' ); ?></h1>
			<div class="notice notice-info"><p>
				<?php esc_html_e( 'Pro adds a PHP deprecation radar, unlimited alert channels and a weekly health report.', 'crashalert' ); ?>
				<a href="https://yodsira.duckdns.org/buy/crashalert" target="_blank" rel="noopener">CrashAlert Pro &rarr;</a>
			</p></div>
			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Saved.', 'crashalert' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpca_settings">
				<?php wp_nonce_field( 'wpca_settings' ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Alerts enabled', 'crashalert' ); ?></th>
						<td><label><input type="checkbox" name="wpca[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?>> <?php esc_html_e( 'Send alerts on fatal errors', 'crashalert' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="wpca-tg-token">Telegram</label></th>
						<td>
							<input type="text" id="wpca-tg-token" class="regular-text" name="wpca[telegram_token]" placeholder="123456:AA…" value="<?php echo esc_attr( $masked ); ?>">
							<p class="description">Bot token от @BotFather (токен скрыт после сохранения).</p>
							<input type="text" class="regular-text" name="wpca[telegram_chat]" placeholder="@channel или 123456789" value="<?php echo esc_attr( $s['telegram_chat'] ); ?>">
							<p class="description">Chat ID или @канал. Добавьте бота в канал администратором.</p>
							<p><button type="button" class="button" id="wpca-test-tg"><?php esc_html_e( 'Send test to Telegram', 'crashalert' ); ?></button> <span id="wpca-test-tg-result"></span></p>
						</td>
					</tr>
					<tr>
						<th>Email</th>
						<td>
							<label><input type="checkbox" name="wpca[email_enabled]" value="1" <?php checked( $s['email_enabled'], 1 ); ?>> <?php esc_html_e( 'Also send email alerts', 'crashalert' ); ?></label><br>
							<input type="email" class="regular-text" name="wpca[email_address]" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" value="<?php echo esc_attr( $s['email_address'] ); ?>">
							<p class="description"><?php esc_html_e( 'Empty = admin email.', 'crashalert' ); ?></p>
						</td>
					</tr>
					<tr>
						<th>Webhook</th>
						<td>
							<input type="url" class="regular-text" name="wpca[webhook_url]" value="<?php echo esc_attr( $s['webhook_url'] ); ?>" placeholder="https://…">
							<p><button type="button" class="button" id="wpca-test-webhook"><?php esc_html_e( 'Send test to webhook', 'crashalert' ); ?></button> <span id="wpca-test-webhook-result"></span></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Rate limit', 'crashalert' ); ?></th>
						<td>
							<input type="number" min="60" max="3600" step="60" name="wpca[rate_window]" value="<?php echo esc_attr( $s['rate_window'] ); ?>"> <?php esc_html_e( 'seconds per identical error', 'crashalert' ); ?>
							<p class="description"><?php esc_html_e( 'One alert per error signature within this window; repeats are counted.', 'crashalert' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Keep history', 'crashalert' ); ?></th>
						<td>
							<input type="number" min="7" max="365" name="wpca[retention_days]" value="<?php echo esc_attr( $s['retention_days'] ); ?>"> <?php esc_html_e( 'days', 'crashalert' ); ?>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
