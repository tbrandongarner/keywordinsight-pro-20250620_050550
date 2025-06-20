<?php
class SettingsFormManager {
    private static $instance = null;
    private $option_name;
    private $page_slug;

    public static function getInstance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->option_name = 'ki_pro_settings';
        $this->page_slug   = 'ki_pro_settings';

        add_action( 'admin_init', array( $this, 'registerSettings' ) );
        add_action( 'admin_menu', array( $this, 'addSettingsPage' ) );
    }

    private function __clone() {}
    private function __wakeup() {}

    public static function activate() {
        if ( false === get_option( 'ki_pro_settings' ) ) {
            add_option( 'ki_pro_settings', array(
                'api_key'         => '',
                'update_interval' => 1,
            ) );
        }
    }

    public function registerSettings() {
        register_setting(
            'ki_pro_settings_group',
            $this->option_name,
            array(
                'sanitize_callback' => array( $this, 'sanitize' ),
                'default'           => array(
                    'api_key'         => '',
                    'update_interval' => 1,
                ),
            )
        );

        add_settings_section(
            'ki_pro_general_section',
            __( 'General Settings', 'keywordinsight' ),
            '__return_false',
            $this->page_slug
        );

        add_settings_field(
            'api_key',
            __( 'API Key', 'keywordinsight' ),
            array( $this, 'renderTextField' ),
            $this->page_slug,
            'ki_pro_general_section',
            array(
                'label_for'   => 'api_key',
                'class'       => 'ki_row',
                'option_name' => $this->option_name,
            )
        );

        add_settings_field(
            'update_interval',
            __( 'Update Interval (hours)', 'keywordinsight' ),
            array( $this, 'renderNumberField' ),
            $this->page_slug,
            'ki_pro_general_section',
            array(
                'label_for'   => 'update_interval',
                'class'       => 'ki_row',
                'option_name' => $this->option_name,
                'min'         => 1,
            )
        );
    }

    public function addSettingsPage() {
        add_options_page(
            __( 'KeywordInsight Pro Settings', 'keywordinsight' ),
            __( 'KeywordInsight', 'keywordinsight' ),
            'manage_options',
            $this->page_slug,
            array( $this, 'render' )
        );
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'KeywordInsight Pro Settings', 'keywordinsight' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'ki_pro_settings_group' );
        do_settings_sections( $this->page_slug );
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function renderTextField( $args ) {
        $options = get_option( $args['option_name'], array(
            'api_key'         => '',
            'update_interval' => 1,
        ) );
        $value = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        printf(
            '<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text"/>',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['option_name'] ),
            esc_attr( $value )
        );
    }

    public function renderNumberField( $args ) {
        $options = get_option( $args['option_name'], array(
            'api_key'         => '',
            'update_interval' => 1,
        ) );
        $value = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
        $min   = isset( $args['min'] ) ? intval( $args['min'] ) : 0;
        printf(
            '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$d"/>',
            esc_attr( $args['label_for'] ),
            esc_attr( $args['option_name'] ),
            esc_attr( $value ),
            $min
        );
    }

    public function sanitize( $input ) {
        $sanitized = array();
        if ( isset( $input['api_key'] ) ) {
            $sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
        }
        if ( isset( $input['update_interval'] ) ) {
            $sanitized['update_interval'] = absint( $input['update_interval'] );
        }
        return $sanitized;
    }
}

SettingsFormManager::getInstance();