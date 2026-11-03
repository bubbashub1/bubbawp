<?php
/**
 * Explicitly read-only access to existing activity records in the current WP database.
 * Not loaded until the Directory feature has passed staging QA.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
final class BH_Activity_Repository_3 {
    /** @var wpdb */
    private $db;

    public function __construct( $db ) {
        $this->db = $db;
    }

    /**
     * Does not assume the WordPress prefix matches the legacy bh_ table prefix.
     */
    public function available() {
        $table = $this->db->get_var(
            $this->db->prepare( 'SHOW TABLES LIKE %s', $this->db->esc_like( 'bh_activities' ) )
        );
        return $table === 'bh_activities';
    }

    /**
     * Minimal public listing fields only. Never query child, contact or account data.
     *
     * @return array|WP_Error
     */
    public function search( $args = array() ) {
        if ( ! $this->available() ) {
            return new WP_Error( 'bh_missing_table', __( 'Activity data is not available in this environment.', 'bubba-hub-manager' ) );
        }
        $page = max( 1, min( 10000, absint( $args['page'] ?? 1 ) ) );
        $per_page = max( 1, min( 24, absint( $args['per_page'] ?? 12 ) ) );
        $search = sanitize_text_field( $args['search'] ?? '' );
        $where = "LOWER(TRIM(COALESCE(status, ''))) IN ('published','publish')";
        $params = array();
        if ( $search !== '' ) {
            $where .= ' AND (title LIKE %s OR description LIKE %s)';
            $like = '%' . $this->db->esc_like( $search ) . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $offset = ( $page - 1 ) * $per_page;
        $sql = "SELECT id, title, slug FROM bh_activities WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        $prepared = $this->db->prepare( $sql, $params );
        $rows = $this->db->get_results( $prepared, ARRAY_A );
        if ( $this->db->last_error ) {
            return new WP_Error( 'bh_query_failed', __( 'Unable to read activity records.', 'bubba-hub-manager' ) );
        }
        return array_map(
            static function ( $row ) {
                return array(
                    'id' => (int) $row['id'],
                    'title' => (string) $row['title'],
                    'slug' => (string) $row['slug'],
                );
            },
            $rows ?: array()
        );
    }
}
