<?php
if (!defined('ABSPATH')) exit;
/** Read-only, bounded suggestions from the same WP activity tables as the directory. */
final class BH_Family_Recommendations_3 {
    public static function fetch($preferences, $profiles = array()) {
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
        $price = $preferences['max_price'] ?? null;
        if ($price !== null && $price !== '' && is_numeric($price) && $price >= 0 && $price <= 10000) {
            $conditions[] = '(a.is_free = 1 OR (a.price_from IS NOT NULL AND a.price_from <= %f))';
            $params[] = (float) $price;
        }
        if (!empty($preferences['term_time'])) $conditions[] = "EXISTS (SELECT 1 FROM `{$s}` st WHERE st.activity_id=a.id AND st.term_time_only=1)";
        $region = strtolower(trim((string) ($preferences['region'] ?? '')));
        if (in_array($region, array('devon', 'cornwall'), true)) {
            $conditions[] = "EXISTS (SELECT 1 FROM `{$av}` ra INNER JOIN `{$v}` rv ON rv.id=ra.venue_id WHERE ra.activity_id=a.id AND LOWER(TRIM(rv.region))=%s)";
            $params[] = $region;
        }
        $days = array('monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7);
        $chosen = array_values(array_intersect_key($days, array_flip((array)($preferences['days'] ?? array()))));
        if ($chosen) {
            $placeholders = implode(',',array_fill(0,count($chosen),'%d'));
            $conditions[] = "EXISTS (SELECT 1 FROM `{$s}` sd WHERE sd.activity_id=a.id AND sd.day_of_week IN ({$placeholders}) AND sd.status='scheduled')";
            $params = array_merge($params,$chosen);
        }
        // Match any child in the household, not just the oldest or youngest.
        // WordPress timezone is authoritative for age calculations.
        $ages = array();
        $today = new DateTimeImmutable('today', wp_timezone());
        foreach ((array)($profiles['children'] ?? array()) as $child) {
            $dob = isset($child['date_of_birth']) ? (string)$child['date_of_birth'] : '';
            $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $dob, wp_timezone());
            if (!$birth || $birth->format('Y-m-d') !== $dob || $birth > $today) continue;
            $diff = $birth->diff($today);
            $months = $diff->y * 12 + $diff->m;
            if ($months <= 216) $ages[$months] = true;
        }
        if ($ages) {
            $age_conditions = array();
            foreach (array_keys($ages) as $months) {
                $age_conditions[] = '((a.age_min_months IS NULL OR a.age_min_months <= %d) AND (a.age_max_months IS NULL OR a.age_max_months >= %d))';
                $params[] = $months; $params[] = $months;
            }
            $conditions[] = '(' . implode(' OR ', $age_conditions) . ')';
        }
        // Distance only when the saved postcode resolves to exactly one distinct
        // venue coordinate; otherwise never present a misleading radius match.
        $origin = null;
        $postcode_for_origin = strtoupper(trim((string)($preferences['postcode'] ?? '')));
        if ($postcode_for_origin !== '') {
            $origins = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT latitude,longitude FROM `{$v}` WHERE REPLACE(UPPER(postcode),' ','')=%s AND latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 2",
                preg_replace('/\\s+/', '', $postcode_for_origin)
            ), ARRAY_A);
            if (count((array)$origins) === 1) {
                $lat = (float)$origins[0]['latitude']; $lng = (float)$origins[0]['longitude'];
                if (abs($lat) <= 90 && abs($lng) <= 180 && ($lat != 0 || $lng != 0)) $origin = array($lat,$lng);
            }
        }
        $radius = (int)($preferences['radius'] ?? 10);
        if ($origin && in_array($radius,array(5,10,15,25,50),true)) {
            $lat = $origin[0]; $lng = $origin[1];
            $lat_delta = $radius / 69.0;
            $lng_delta = $radius / max(.01,69.0*cos(deg2rad($lat)));
            $distance = '(3958.7613 * 2 * ASIN(SQRT(POW(SIN(RADIANS(vd.latitude - %f)/2),2) + COS(RADIANS(%f))*COS(RADIANS(vd.latitude))*POW(SIN(RADIANS(vd.longitude - %f)/2),2))))';
            $conditions[] = "EXISTS (SELECT 1 FROM `{$av}` ad INNER JOIN `{$v}` vd ON vd.id=ad.venue_id WHERE ad.activity_id=a.id AND vd.latitude BETWEEN %f AND %f AND vd.longitude BETWEEN %f AND %f AND {$distance} <= %f)";
            array_push($params,max(-90,$lat-$lat_delta),min(90,$lat+$lat_delta),max(-180,$lng-$lng_delta),min(180,$lng+$lng_delta),$lat,$lat,$lng,$radius);
        }
        $town = trim((string)($preferences['town'] ?? ''));
        $postcode = trim((string)($preferences['postcode'] ?? ''));
        if (!$origin && ($town !== '' || $postcode !== '')) {
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
