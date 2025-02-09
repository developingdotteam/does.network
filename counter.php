<?php
// counter.php

// Set the default timezone to UTC explicitly
date_default_timezone_set('UTC');

// DISCLAIMER: This script logs only rough location data (city, region, country)
// together with a UTC timestamp (human-readable, formatted as "Y-m-d H:i:s")
// for usage trend analysis. No full IP addresses or personally identifiable details
// are stored. Ensure this process complies with GDPR, CCPA, and other applicable
// regulations, and update your privacy policy accordingly.

// Path to counter file
$counterFile = 'counter.txt';

// Get the current timestamp in UTC
$currentTimeEpoch = time();
// Format the timestamp as "Y-m-d H:i:s", e.g. "2025-02-07 19:32:49"
$currentTimeFormatted = gmdate('Y-m-d H:i:s', $currentTimeEpoch);

// Define time intervals (in seconds)
$oneHour   = 3600;
$oneDay    = 86400;
$sevenDays = 604800;

// Initialize entry array
$entries = [];

// Read the file if it exists and read past log entries from it
// Each non-header line has the format: "YYYY-MM-DD HH:MM:SS<!--space-->location_info"
if (file_exists($counterFile)) {
    $lines = file($counterFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip header lines (starting with "#")
        if (strpos($line, '#') === 0) {
            continue;
        }
        // We assume the first 19 characters are the timestamp ("Y-m-d H:i:s")
        if (strlen($line) < 19) {
            continue;
        }
        $timestampStr = substr($line, 0, 19);
        // Everything after position 19 is the location (trim leading/trailing spaces)
        $location = trim(substr($line, 19));
        $timestampEpoch = strtotime($timestampStr);
        if ($timestampEpoch !== false) {
            $entries[] = [
                'timestampEpoch' => $timestampEpoch,
                'timestampStr'   => $timestampStr,
                'location'       => !empty($location) ? $location : 'Unknown'
            ];
        }
    }
}

// Get the visitor's IP address
$visitorIP = $_SERVER['REMOTE_ADDR'];

// Function to get a rough location from the visitor's IP using ip-api.com.
// Note: ip-api.com returns rough location data.
function getApproximateLocation($ip) {
    $apiUrl = "http://ip-api.com/json/$ip?fields=status,city,regionName,country";
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Set a timeout for the API call
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] === 'success') {
        // Return a string in the format "City, Region, Country"
        $locationParts = array_filter([
            $data['city'] ?? '',
            $data['regionName'] ?? '',
            $data['country'] ?? ''
        ]);
        return implode(", ", $locationParts);
    }
    return 'Unknown';
}

// Retrieve the approximate location for the current visitor
$currentLocation = getApproximateLocation($visitorIP);

// Append the current log entry to the entries array
$entries[] = [
    'timestampEpoch' => $currentTimeEpoch,
    'timestampStr'   => $currentTimeFormatted,
    'location'       => $currentLocation
];

// Filter out any entries older than 7 days (using epoch timestamps)
$entries = array_filter($entries, function ($entry) use ($currentTimeEpoch, $sevenDays) {
    return ($currentTimeEpoch - $entry['timestampEpoch']) <= $sevenDays;
});

// Order entries so that the newest entry comes first
usort($entries, function ($a, $b) {
    return $b['timestampEpoch'] - $a['timestampEpoch'];
});

// Calculate counts for the last 1h, 1d, and 7d
$count1h = $count1d = 0;
$count7d = count($entries);

foreach ($entries as $entry) {
    $diff = $currentTimeEpoch - $entry['timestampEpoch'];
    if ($diff <= $oneHour) {
        $count1h++;
    }
    if ($diff <= $oneDay) {
        $count1d++;
    }
}

// Prepare header lines (timestamps are in UTC, shown in human-readable format)
$header  = "# Visitors in Last 1h: $count1h" . PHP_EOL;
$header .= "# Visitors in Last 1d: $count1d" . PHP_EOL;
$header .= "# Visitors in Last 7d: $count7d" . PHP_EOL;
$header .= "#" . PHP_EOL; // Blank header line

// Prepare body lines: each line in the format "timestamp location_info"
// The timestamp occupies the first 19 characters.
$body = '';
foreach ($entries as $entry) {
    $body .= $entry['timestampStr'] . ' ' . $entry['location'] . PHP_EOL;
}

// Write back to the counter file (this overwrites the file with the new log)
file_put_contents($counterFile, $header . $body);

// Optionally, output a confirmation message to the browser
header('Content-Type: text/plain');
echo "Visitor count and location logged successfully.";
?>
