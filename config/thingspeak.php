<?php
/**
 * ThingSpeak API Helper
 * Handles fetching data from ThingSpeak channels
 */

// ThingSpeak API Configuration
define('THINGSPEAK_API_URL', 'https://api.thingspeak.com');
define('WATER_TANK_CHANNEL_ID', '3166921'); // Replace with actual channel ID
define('WATER_TANK_READ_API', '35FGJDU4TC9ZS49D'); // Replace with actual read API key
define('CLEAN_FEEDBACK_CHANNEL_ID', '3166921'); // Replace with actual channel ID
define('CLEAN_FEEDBACK_READ_API', '35FGJDU4TC9ZS49D'); // Replace with actual read API key

/**
 * Fetch latest water tank level from ThingSpeak
 * @return array|false Water tank data or false on error
 */
function getWaterTankLevel() {
    $url = THINGSPEAK_API_URL . '/channels/' . WATER_TANK_CHANNEL_ID . '/feeds.json?api_key=' . WATER_TANK_READ_API . '&results=1';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['feeds']) && count($data['feeds']) > 0) {
            return $data['feeds'][0];
        }
    }
    return false;
}

/**
 * Fetch clean feedback from ThingSpeak
 * @param string $days Number of days to fetch (optional, default 1)
 * @return array Array of feedback entries
 */
function getCleanFeedback($days = 1) {
    $results = min($days * 100, 8000); // ThingSpeak limit is 8000 results per API call
    $url = THINGSPEAK_API_URL . '/channels/' . CLEAN_FEEDBACK_CHANNEL_ID . '/feeds.json?api_key=' . CLEAN_FEEDBACK_READ_API . '&results=' . $results;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode === 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['feeds'])) {
            return $data['feeds'];
        }
    }
    return [];
}

/**
 * Count feedback by type for a specific date
 * ThingSpeak channel stores button responses across separate fields:
 *  - field3 => "bad"
 *  - field4 => "normal"
 *  - field5 => "good"
 * Each feed row may have one of these fields set to a truthy / numeric value.
 * @param array $feedbacks Array of feedback entries from ThingSpeak
 * @param string $date Date in Y-m-d format (optional, default today)
 * @return array Counts of [bad, normal, good]
 */
function countFeedbackByDate($feedbacks, $date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }

    $counts = ['bad' => 0, 'normal' => 0, 'good' => 0];

    foreach ($feedbacks as $entry) {
        $entry_date = substr($entry['created_at'], 0, 10);
        if ($entry_date !== $date) {
            continue;
        }

        // field3 => bad, field4 => normal, field5 => good
        $bad_val = isset($entry['field3']) ? intval($entry['field3']) : 0;
        $normal_val = isset($entry['field4']) ? intval($entry['field4']) : 0;
        $good_val = isset($entry['field5']) ? intval($entry['field5']) : 0;

        if ($bad_val > 0) {
            $counts['bad'] += $bad_val;
        }
        if ($normal_val > 0) {
            $counts['normal'] += $normal_val;
        }
        if ($good_val > 0) {
            $counts['good'] += $good_val;
        }
    }

    return $counts;
}

/**
 * Group feedback by date
 * Aggregates counts per date using field3/4/5 mapping
 * @param array $feedbacks Array of feedback entries from ThingSpeak
 * @return array Grouped by date (newest first)
 */
function groupFeedbackByDate($feedbacks) {
    $grouped = [];

    foreach ($feedbacks as $entry) {
        $date = substr($entry['created_at'], 0, 10);

        if (!isset($grouped[$date])) {
            $grouped[$date] = ['bad' => 0, 'normal' => 0, 'good' => 0];
        }

        $bad_val = isset($entry['field3']) ? intval($entry['field3']) : 0;
        $normal_val = isset($entry['field4']) ? intval($entry['field4']) : 0;
        $good_val = isset($entry['field5']) ? intval($entry['field5']) : 0;

        if ($bad_val > 0) {
            $grouped[$date]['bad'] += $bad_val;
        }
        if ($normal_val > 0) {
            $grouped[$date]['normal'] += $normal_val;
        }
        if ($good_val > 0) {
            $grouped[$date]['good'] += $good_val;
        }
    }

    krsort($grouped); // Sort by date descending (newest first)
    return $grouped;
}
?>
