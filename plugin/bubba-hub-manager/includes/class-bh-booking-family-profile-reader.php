<?php
/**
 * Read-only family profile selector for the existing Ninja Forms integration.
 * Registers only a dynamic Select-options filter; no booking, payment or schema writes.
 */
defined( 'ABSPATH' ) || exit;

final class BH_Booking_Family_Profile_Reader {
    /** Configure a Ninja Forms Select field with this exact Field Key. */
    const NINJA_FIELD_KEY = 'bh_family_profile_id';

    /** Register only the dynamic options filter; no booking or payment hooks. */
    public static function register() {
        if ( function_exists( 'add_filter' ) ) {
            add_filter( 'ninja_forms_render_options', array( __CLASS__, 'filter_ninja_options' ), 20, 2 );
        }
    }

    /**
     * Populate one explicitly keyed Ninja Forms Select field with owned child IDs.
     * A guest/manual option remains available; pregnancy profiles are not attendees.
     */
    public static function filter_ninja_options( $options, $settings ) {
        if ( ! is_array( $settings ) || ( $settings['key'] ?? '' ) !== self::NINJA_FIELD_KEY ) {
            return $options;
        }

        $dynamic = array(
            array( 'label' => 'Choose a child', 'value' => '', 'calc' => 0, 'selected' => false ),
        );

        foreach ( self::owned_options() as $profile ) {
            if ( $profile['type'] !== 'child' ) {
                continue;
            }
            $dynamic[] = array(
                'label' => $profile['label'],
                'value' => (string) $profile['id'],
                'calc' => 0,
                'selected' => false,
            );
        }

        $dynamic[] = array(
            'label' => 'I will enter attendee details manually',
            'value' => 'guest',
            'calc' => 0,
            'selected' => false,
        );

        return $dynamic;
    }
    /**
     * Return only profiles owned by the current authenticated WordPress user.
     * Never trust a posted user ID or profile ID for ownership.
     *
     * @return array<int,array{id:int,type:string,label:string}>
     */
    public static function owned_options() {
        if ( ! is_user_logged_in() ) {
            return array();
        }
        global $wpdb;
        $user_id = get_current_user_id();
        $table = $wpdb->prefix . 'bh_family_profiles';
        // Do not create tables or fall back to legacy IDs: legacy bh_users IDs
        // are not guaranteed to match WordPress user IDs.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
        );
        if ( $table_exists !== $table ) {
            return array();
        }
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, profile_type, display_name FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT 100",
                $user_id
            ),
            ARRAY_A
        );
        $options = array();
        foreach ( (array) $rows as $row ) {
            $type = (string) $row['profile_type'];
            if ( ! in_array( $type, array( 'child', 'bump' ), true ) ) {
                continue;
            }
            $options[] = array(
                'id'    => (int) $row['id'],
                'type'  => $type,
                'label' => sanitize_text_field( (string) $row['display_name'] ),
            );
        }
        return $options;
    }

    /** Revalidate a child attendee selection against the logged-in user's own profiles. */
    public static function owns_child_profile( $profile_id ) {
        if ( ! is_scalar( $profile_id ) || ! ctype_digit( (string) $profile_id ) ) {
            return false;
        }
        $id = (int) $profile_id;
        if ( $id <= 0 ) {
            return false;
        }
        foreach ( self::owned_options() as $option ) {
            if ( $option['id'] === $id && $option['type'] === 'child' ) {
                return true;
            }
        }
        return false;
    }

    /** Revalidate submitted selection against the logged-in user's own profiles. */
    public static function owns_profile( $profile_id ) {
        if ( ! is_scalar( $profile_id ) || ! ctype_digit( (string) $profile_id ) ) {
            return false;
        }
        $id = (int) $profile_id;
        if ( $id <= 0 ) {
            return false;
        }
        foreach ( self::owned_options() as $option ) {
            if ( $option['id'] === $id ) {
                return true;
            }
        }
        return false;
    }
}
