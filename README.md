# WP-Mlm-Rest-API-plugin
Oath2 and OpenID Rest API login auth plugin MLM
Заменяет стандартную форму логина на автологин OpenID. Также получает данные о реферале через oAuth2 протокол

		add_action('admin_init', array( $this, 'init'));
		add_action('init', array( $this, 'init_session'), 1);
		add_action('login_init', array( $this, 'login'));
		add_filter('authenticate', array( $this, 'auth'));
		remove_filter('authenticate', 'wp_authenticate_username_password',  20, 3 );
		remove_filter('authenticate', 'wp_authenticate_email_password',     20, 3 );
		remove_filter('authenticate', 'wp_authenticate_spam_check',         99    );
		add_shortcode('mlm_referral', array( $this, 'mlm_referral'));
