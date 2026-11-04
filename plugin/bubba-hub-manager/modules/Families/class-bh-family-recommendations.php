<?php
if (!defined('ABSPATH')) exit;
/** Read-only, bounded suggestions from the same WP activity tables as the directory. */
final class BH_Family_Recommendations_3 {
    public static function fetch($preferences) {
        if (wp_parse_url(home_url(), PHP_URL_HOST) !== 'staging.bubbahub.co.uk') return array();
        global $wpdb;
        $a = $wpdb->prefix . 'activities'; $v = $wpdb->prefix . 'venues';
        $av = $wpdb->prefix . 'activity_venues'; $s = $wpdb->prefix . 'sessions';
        foreach (array($a,$v,$av,$s) as $table) {
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) return array();
        }
        $conditions = array("a.status IN ('published','publish')");
        $params = array();
        if (!empty($preferences['free_only'])) $conditions[] = 'a.is_free = 1';
        if (!empty($preferences['term_time'])) $conditions[] = "EXISTS (SELECT 1 FROM `{$s}` st WHERE st.activity_id=a.id AND st.term_time_only=1)";
        $days = array('monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7);
        $chosen = array_values(array_intersect_key($days, array_flip((array)($preferences['days'] ?? array()))));
        if ($chosen) {
            $placeholders = implode(',',array_fill(0,count($chosen),'%d'));
            $conditions[] = "EXISTS (SELECT 1 FROM `{$s}` sd WHERE sd.activity_id=a.id AND sd.day_of_week IN ({$placeholders}) AND sd.status='scheduled')";
            $params = array_merge($params,$chosen);
        }
        $town = trim((string)($preferences['town'] ?? ''));
        $postcode = trim((string)($preferences['postcode'] ?? ''));
        if ($town !== '' || $postcode !== '') {
            $location = array();
            if ($town !== '') { $location[] = 'v.town LIKE %s'; $params[] = '%' . $wpdb->esc_like($town) . '%'; }
            if ($postcode !== '') { $location[] = 'v.postcode LIKE %s'; $params[] = $wpdb->esc_like($postcode) . '%'; }
            $conditions[] = "EXISTS (SELECT 1 FROM `{$av}` av INNER JOIN `{$v}` v ON v.id=av.venue_id WHERE av.activity_id=a.id AND (" . implode(' OR ', $location) . '))';
        }
        $map = array(
            'baby_groups'=>array('baby','parent and baby'), 'toddler_groups'=>array('toddler','stay and play'),
            'sensory'=>array('sensory'), 'music'=>array('music','movement'), 'outdoors'=>array('outdoor','nature'),
            'arts'=>array('art','craft'), 'libraries'=>array('library','story'), 'sports'=>array('sport','swim'),
            'parent_support'=>array('parent support','breastfeeding'), 'pregnancy'=>array('pregnancy','antenatal'),
            'free_events'=>array('event')
        );
        $terms = array();
        foreach ((array)($preferences['interests'] ?? array()) as $interest) {
            foreach (($map[$interest] ?? array()) as $term) $terms[$term] = true;
        }
        if ($terms) {
            $likes = array();
            foreach (array_keys($terms) as $term) {
                $likes[] = '(a.category LIKE %s OR a.title LIKE %s)';
                $pattern = '%' . $wpdb->esc_like($term) . '%';
                $params[] = $pattern; $params[] = $pattern;
            }
            $conditions[] = '(' . implode(' OR ', $likes) . ')';
        }
        $sql = "SELECT a.id,a.title,a.slug,a.category,a.is_free,
            (SELECT v.town FROM `{$av}` av INNER JOIN `{$v}` v ON v.id=av.venue_id WHERE av.activity_id=a.id ORDER BY v.name LIMIT 1) AS town
            FROM `{$a}` a WHERE " . implode(' AND ', $conditions) . " ORDER BY a.id DESC LIMIT 6";
        $rows = $wpdb->get_results($params ? $wpdb->prepare($sql,$params) : $sql, ARRAY_A);
        return is_array($rows) ? $rows : array();
    }
}
