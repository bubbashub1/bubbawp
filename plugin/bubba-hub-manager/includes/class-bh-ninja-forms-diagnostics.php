<?php
/** Staging-only, read-only Ninja Forms discovery for the BubbaWP booking adapter. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BH_Ninja_Forms_Diagnostics {
    public static function register() {
        if ( strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) !== 'staging.bubbahub.co.uk' ) { return; }
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
    }

    public static function menu() {
        add_submenu_page( 'bubba-hub-3-status', 'Ninja Forms Booking Check', 'Ninja Forms Check', 'manage_options', 'bh-ninja-forms-check', array( __CLASS__, 'render' ) );
    }

    private static function model_settings( $model ) {
        if ( is_object( $model ) && method_exists( $model, 'get_settings' ) ) {
            $settings = $model->get_settings();
            return is_array( $settings ) ? $settings : array();
        }
        return is_array( $model ) && isset( $model['settings'] ) && is_array( $model['settings'] )
            ? $model['settings'] : ( is_array( $model ) ? $model : array() );
    }

    private static function model_id( $model, $settings ) {
        if ( is_object( $model ) && method_exists( $model, 'get_id' ) ) { return absint( $model->get_id() ); }
        return isset( $model['id'] ) ? absint( $model['id'] ) : ( isset( $settings['id'] ) ? absint( $settings['id'] ) : 0 );
    }

    private static function models_array( $models ) {
        if ( is_array( $models ) ) { return $models; }
        if ( $models instanceof Traversable ) { return iterator_to_array( $models ); }
        return array();
    }

    public static function inspect() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error( 'bh_forbidden', 'Administrator access required.' );
        }
        if ( ! function_exists( 'get_plugins' ) ) { require_once ABSPATH . 'wp-admin/includes/plugin.php'; }
        $plugin_version = '';
        foreach ( get_plugins() as $basename => $plugin ) {
            if ( strpos( $basename, 'ninja-forms/' ) === 0 ) {
                $plugin_version = isset( $plugin['Version'] ) ? (string) $plugin['Version'] : '';
                break;
            }
        }
        $result = array(
            'plugin_version' => $plugin_version ?: 'Not installed',
            'api_available' => function_exists( 'Ninja_Forms' ),
            'forms' => array(),
            'form_count' => 0,
            'read_only' => true,
            'submission_data_read' => false,
            'booking_writes_performed' => false,
        );
        if ( ! $result['api_available'] ) { return $result; }
        try {
            $forms = self::models_array( Ninja_Forms()->form()->get_forms() );
            foreach ( array_slice( $forms, 0, 100 ) as $form ) {
                $settings = self::model_settings( $form );
                $form_id = self::model_id( $form, $settings );
                $fields = array();
                $actions = array();
                if ( $form_id ) {
                    $form_model = Ninja_Forms()->form( $form_id );
                    if ( is_object( $form_model ) && method_exists( $form_model, 'get_fields' ) ) {
                        foreach ( array_slice( self::models_array( $form_model->get_fields() ), 0, 100 ) as $field ) {
                            $field_settings = self::model_settings( $field );
                            $fields[] = array(
                                'id' => self::model_id( $field, $field_settings ),
                                'key' => isset( $field_settings['key'] ) ? sanitize_key( $field_settings['key'] ) : '',
                                'label' => isset( $field_settings['label'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $field_settings['label'] ) ) : '',
                                'type' => isset( $field_settings['type'] ) ? sanitize_key( $field_settings['type'] ) : '',
                            );
                        }
                    }
                    if ( is_object( $form_model ) && method_exists( $form_model, 'get_actions' ) ) {
                        foreach ( array_slice( self::models_array( $form_model->get_actions() ), 0, 30 ) as $action ) {
                            $action_settings = self::model_settings( $action );
                            $actions[] = array(
                                'id' => self::model_id( $action, $action_settings ),
                                'type' => isset( $action_settings['type'] ) ? sanitize_key( $action_settings['type'] ) : '',
                            );
                        }
                    }
                }
                $result['forms'][] = array(
                    'id' => $form_id,
                    'title' => isset( $settings['title'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $settings['title'] ) ) : '(untitled)',
                    'fields' => $fields,
                    'actions' => $actions,
                );
            }
            $result['form_count'] = count( $result['forms'] );
        } catch ( Throwable $error ) {
            $result['api_error'] = 'Ninja Forms API could not be read safely: ' . sanitize_text_field( $error->getMessage() );
        }
        return $result;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Not permitted.', 'bubba-hub-manager' ), '', array( 'response' => 403 ) ); }
        $result = self::inspect();
        echo '<div class="wrap"><h1>Ninja Forms — staging check</h1>';
        echo '<p>Read-only discovery of form definitions. No submission contents, parent details, child details or booking records are read or changed.</p>';
        if ( is_wp_error( $result ) ) { echo '<p>' . esc_html( $result->get_error_message() ) . '</p></div>'; return; }
        echo '<p><strong>Ninja Forms version:</strong> ' . esc_html( $result['plugin_version'] ) . '</p>';
        if ( empty( $result['api_available'] ) ) { echo '<p>Ninja Forms API is not currently available.</p></div>'; return; }
        if ( isset( $result['api_error'] ) ) { echo '<p>' . esc_html( $result['api_error'] ) . '</p></div>'; return; }
        foreach ( $result['forms'] as $form ) {
            echo '<h2>' . esc_html( $form['title'] ) . ' <small>(Form ID ' . absint( $form['id'] ) . ')</small></h2>';
            echo '<h3>Fields</h3><table class="widefat striped"><thead><tr><th>Field ID</th><th>Field key</th><th>Label</th><th>Type</th></tr></thead><tbody>';
            foreach ( $form['fields'] as $field ) {
                echo '<tr><td>' . absint( $field['id'] ) . '</td><td><code>' . esc_html( $field['key'] ) . '</code></td><td>' . esc_html( $field['label'] ) . '</td><td>' . esc_html( $field['type'] ) . '</td></tr>';
            }
            echo '</tbody></table><h3>Configured actions</h3><ul>';
            foreach ( $form['actions'] as $action ) { echo '<li>' . esc_html( $action['type'] ) . ' (ID ' . absint( $action['id'] ) . ')</li>'; }
            echo '</ul>';
        }
        echo '<p><strong>Safety:</strong> This screen is read-only. Booking adapter remains disabled; no submissions or booking records were changed.</p></div>';
    }
}
