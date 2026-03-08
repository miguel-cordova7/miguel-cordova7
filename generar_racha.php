<?php


declare(strict_types=1);

// --- CONFIGURACIÓN DE USUARIO ---
$user = "miguel-cordova7";
$token = getenv('GH_TOKEN');
if (!$token) die("Error: GH_TOKEN no configurado.\n");

// --- REPLICACIÓN DE DATOS EXTERNOS (Themes & Translations) ---
$THEMES = [
    "algolia" => [
        "bg_color" => "0d1117", "fire" => "00a8f3", "ring" => "00a8f3",
        "currStreakNum" => "28a745", "currStreakLabel" => "28a745",
        "sideNums" => "00a8f3", "sideLabels" => "fff", "dates" => "768390",
        "stroke" => "444", "border" => "0000", "background" => "0d1117"
    ]
];

$TRANSLATIONS = [
    "en" => [
        "Total Contributions" => "Total Contributions",
        "Current Streak" => "Current Streak",
        "Longest Streak" => "Longest Streak",
        "Present" => "Present",
        "date_format" => "M j, Y"
    ]
];

// --- MOCK DE FUNCIONES DE SOPORTE ---
function getGitHubToken() { return getenv('GH_TOKEN'); }
function isWhitelisted($user) { return true; }
function getRequestedTheme($params) { global $THEMES; return $THEMES["algolia"]; }
function getTranslations($locale) { global $TRANSLATIONS; return $TRANSLATIONS["en"]; }

// --- CÓDIGO ORIGINAL DE stats.php ---

function buildContributionGraphQuery(string $user, int $year): string {
    $start = "$year-01-01T00:00:00Z"; $end = "$year-12-31T23:59:59Z";
    return "query { user(login: \"$user\") { createdAt contributionsCollection(from: \"$start\", to: \"$end\") { contributionYears contributionCalendar { weeks { contributionDays { contributionCount date } } } } } }";
}

function getGraphQLCurlHandle(string $query, string $token): CurlHandle {
    $ch = curl_init("https://api.github.com/graphql");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: bearer $token", "Content-Type: application/json", "User-Agent: Streak-Stats"],
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(["query" => $query]),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true
    ]);
    return $ch;
}

function executeContributionGraphRequests(string $user, array $years): array {
    $requests = [];
    foreach ($years as $year) { $requests[$year] = getGraphQLCurlHandle(buildContributionGraphQuery($user, $year), getGitHubToken()); }
    $multi = curl_multi_init();
    foreach ($requests as $handle) { curl_multi_add_handle($multi, $handle); }
    $running = null; do { curl_multi_exec($multi, $running); } while ($running);
    $responses = [];
    foreach ($requests as $year => $handle) {
        $contents = curl_multi_getcontent($handle);
        $responses[$year] = json_decode($contents);
        curl_multi_remove_handle($multi, $handle);
    }
    curl_multi_close($multi);
    return $responses;
}

function getContributionDates(string $user): array {
    $currentYear = intval(date("Y"));
    $responses = executeContributionGraphRequests($user, [$currentYear]);
    $userCreatedYear = intval(explode("-", $responses[$currentYear]->data->user->createdAt)[0]);
    $years = range(max($userCreatedYear, 2005), $currentYear - 1);
    if (!empty($years)) $responses += executeContributionGraphRequests($user, $years);
    
    $contributions = [];
    ksort($responses);
    foreach ($responses as $graph) {
        foreach ($graph->data->user->contributionsCollection->contributionCalendar->weeks as $week) {
            foreach ($week->contributionDays as $day) { $contributions[$day->date] = $day->contributionCount; }
        }
    }
    return $contributions;
}

function getContributionStats(array $contributions): array {
    $today = array_key_last($contributions);
    $stats = [
        "totalContributions" => 0, "firstContribution" => array_key_first($contributions),
        "longestStreak" => ["start" => "", "end" => "", "length" => 0],
        "currentStreak" => ["start" => "", "end" => "", "length" => 0]
    ];
    $current = ["start" => "", "end" => "", "length" => 0];
    foreach ($contributions as $date => $count) {
        $stats["totalContributions"] += $count;
        if ($count > 0) {
            if ($current["length"] == 0) $current["start"] = $date;
            $current["length"]++; $current["end"] = $date;
            if ($current["length"] > $stats["longestStreak"]["length"]) $stats["longestStreak"] = $current;
        } else if ($date != $today) { $current = ["start" => $today, "end" => $today, "length" => 0]; }
    }
    $stats["currentStreak"] = $current;
    return $stats;
}

// --- CÓDIGO ORIGINAL DE card.php ---

function formatDate(string $dateString, string $locale = "en"): string {
    $date = new DateTime($dateString);
    return $date->format(date("Y") == $date->format("Y") ? "M j" : "M j, Y");
}

function generateCard(array $stats): string {
    $theme = getRequestedTheme([]);
    $locale = getTranslations("en");
    
    $totalContributions = number_format($stats["totalContributions"]);
    $totalRange = formatDate($stats["firstContribution"]) . " - Present";
    $currentStreak = $stats["currentStreak"]["length"];
    $currentRange = formatDate($stats["currentStreak"]["start"]) . " - " . formatDate($stats["currentStreak"]["end"]);
    $longestStreak = $stats["longestStreak"]["length"];
    $longestRange = formatDate($stats["longestStreak"]["start"]) . " - " . formatDate($stats["longestStreak"]["end"]);

    return "
    <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 495 195' width='495px' height='195px' style='isolation: isolate'>
        <style>
            @keyframes currstreak { 0% { font-size: 3px; opacity: 0.2; } 80% { font-size: 34px; opacity: 1; } 100% { font-size: 28px; opacity: 1; } }
            @keyframes fadein { 0% { opacity: 0; } 100% { opacity: 1; } }
        </style>
        <defs>
            <mask id='mask_out_ring_behind_fire'>
                <rect width='495' height='195' fill='white'/>
                <ellipse cx='247.5' cy='32' rx='13' ry='18' fill='black'/>
            </mask>
        </defs>
        <rect width='494' height='194' x='0.5' y='0.5' fill='#{$theme['bg_color']}' rx='4.5' stroke='none'/>
        <g stroke='#{$theme['stroke']}' stroke-opacity='0.2'>
            <line x1='165' y1='28' x2='165' y2='170' />
            <line x1='330' y1='28' x2='330' y2='170' />
        </g>
        <g transform='translate(82.5, 48)' text-anchor='middle'>
            <text x='0' y='32' fill='#{$theme['sideNums']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>{$totalContributions}</text>
            <text x='0' y='64' fill='#{$theme['sideLabels']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.7s'>Total Contributions</text>
            <text x='0' y='88' fill='#{$theme['dates']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.8s'>{$totalRange}</text>
        </g>
        <g transform='translate(247.5, 48)' text-anchor='middle'>
            <g mask='url(#mask_out_ring_behind_fire)'>
                <circle cx='0' cy='23' r='40' fill='none' stroke='#{$theme['ring']}' stroke-width='5' style='opacity: 0; animation: fadein 0.5s linear forwards 0.4s'></circle>
            </g>
            <g transform='translate(0, -28.5)' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>
                <path d='M 1.5 0.67 C 1.5 0.67 2.24 3.32 2.24 5.47 C 2.24 7.53 0.89 9.2 -1.17 9.2 C -3.23 9.2 -4.79 7.53 -4.79 5.47 L -4.76 5.11 C -6.78 7.51 -8 10.62 -8 13.99 C -8 18.41 -4.42 22 0 22 C 4.42 22 8 18.41 8 13.99 C 8 8.6 5.41 3.79 1.5 0.67 Z M -0.29 19 C -2.07 19 -3.51 17.6 -3.51 15.86 C -3.51 14.24 -2.46 13.1 -0.7 12.74 C 1.07 12.38 2.9 11.53 3.92 10.16 C 4.31 11.45 4.51 12.81 4.51 14.2 C 4.51 16.85 2.36 19 -0.29 19 Z' fill='#{$theme['fire']}'/>
            </g>
            <text x='0' y='32' fill='#{$theme['currStreakNum']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='28px' style='animation: currstreak 0.6s linear forwards'>{$currentStreak}</text>
            <text x='0' y='60' fill='#{$theme['currStreakLabel']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>Current Streak</text>
            <text x='0' y='80' fill='#{$theme['dates']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>{$currentRange}</text>
        </g>
        <g transform='translate(412.5, 48)' text-anchor='middle'>
            <text x='0' y='32' fill='#{$theme['sideNums']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.2s'>{$longestStreak}</text>
            <text x='0' y='64' fill='#{$theme['sideLabels']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.3s'>Longest Streak</text>
            <text x='0' y='88' fill='#{$theme['dates']}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.4s'>{$longestRange}</text>
        </g>
    </svg>";
}

// --- EJECUCIÓN FINAL ---
$dates = getContributionDates($user);
$stats = getContributionStats($dates);
file_put_contents("racha.svg", generateCard($stats));
echo "SVG generado con éxito.\n";
