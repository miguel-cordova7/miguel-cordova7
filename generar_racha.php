<?php


declare(strict_types=1);

// --- 1. CONFIGURACIÓN ---
$user = "miguel-cordova7";
$token = getenv('GH_TOKEN');
if (!$token) die("Error: GH_TOKEN no configurado.\n");

// --- 2. LOGICA DE DATOS (stats.php completo) ---
function getGraphQLCurlHandle(string $query, string $token): CurlHandle {
    $headers = ["Authorization: bearer $token", "Content-Type: application/json", "User-Agent: Streak-Stats"];
    $ch = curl_init("https://api.github.com/graphql");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["query" => $query]),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true
    ]);
    return $ch;
}

function getContributionGraphs(string $user, string $token): array {
    $currentYear = intval(date("Y"));
    $query = "query { user(login: \"$user\") { createdAt contributionsCollection { contributionCalendar { weeks { contributionDays { contributionCount date } } } } } }";
    $ch = getGraphQLCurlHandle($query, $token);
    $response = json_decode(curl_exec($ch));
    curl_close($ch);
    
    $contributions = [];
    $weeks = $response->data->user->contributionsCollection->contributionCalendar->weeks;
    foreach ($weeks as $week) {
        foreach ($week->contributionDays as $day) {
            $contributions[$day->date] = $day->contributionCount;
        }
    }
    return [
        "contributions" => $contributions,
        "total" => array_sum($contributions),
        "firstDay" => array_key_first($contributions)
    ];
}

function getContributionStats(array $contributions): array {
    $today = array_key_last($contributions);
    $stats = [
        "totalContributions" => array_sum($contributions),
        "firstContribution" => array_key_first($contributions),
        "longestStreak" => ["start" => "", "end" => "", "length" => 0],
        "currentStreak" => ["start" => "", "end" => "", "length" => 0]
    ];
    $current = ["start" => "", "end" => "", "length" => 0];
    foreach ($contributions as $date => $count) {
        if ($count > 0) {
            if ($current["length"] == 0) $current["start"] = $date;
            $current["length"]++; $current["end"] = $date;
            if ($current["length"] > $stats["longestStreak"]["length"]) $stats["longestStreak"] = $current;
        } else if ($date != $today) {
            $current = ["start" => $today, "end" => $today, "length" => 0];
        }
    }
    $stats["currentStreak"] = $current;
    return $stats;
}

// --- 3. LOGICA VISUAL (card.php completo) ---
function generateCard(array $stats): string {
    // Tema Algolia Original
    $theme = [
        "bg_color" => "#0d1117", "fire" => "#00a8f3", "ring" => "#00a8f3",
        "currStreakNum" => "#28a745", "currStreakLabel" => "#28a745",
        "sideNums" => "#00a8f3", "sideLabels" => "#fff", "dates" => "#768390",
        "stroke" => "#444", "hide_border" => "true"
    ];

    $totalContributions = number_format($stats["totalContributions"]);
    $totalRange = date("M j, Y", strtotime($stats["firstContribution"])) . " - Present";
    $currentStreak = $stats["currentStreak"]["length"];
    $currentRange = date("M j", strtotime($stats["currentStreak"]["start"])) . " - " . date("M j", strtotime($stats["currentStreak"]["end"]));
    $longestStreak = $stats["longestStreak"]["length"];
    $longestRange = date("M j", strtotime($stats["longestStreak"]["start"])) . " - " . date("M j", strtotime($stats["longestStreak"]["end"]));

    // Coordenadas originales de card.php
    $cardWidth = 495; $cardHeight = 195;
    $currentStreakOffset = 247.5; 

    return "
    <svg xmlns='http://www.w3.org/2000/svg' width='{$cardWidth}' height='{$cardHeight}' viewBox='0 0 {$cardWidth} {$cardHeight}' style='isolation: isolate'>
        <style>
            @keyframes currstreak { 0% { font-size: 3px; opacity: 0.2; } 80% { font-size: 34px; opacity: 1; } 100% { font-size: 28px; opacity: 1; } }
            @keyframes fadein { 0% { opacity: 0; } 100% { opacity: 1; } }
            .stat { font-family: 'Segoe UI', Ubuntu, sans-serif; font-weight: 700; }
            .label { font-family: 'Segoe UI', Ubuntu, sans-serif; font-weight: 400; }
            .date { font-family: 'Segoe UI', Ubuntu, sans-serif; font-weight: 400; }
        </style>
        <defs>
            <clipPath id='outer_rectangle'><rect width='{$cardWidth}' height='{$cardHeight}' rx='4.5'/></clipPath>
            <mask id='mask_out_ring_behind_fire'>
                <rect width='{$cardWidth}' height='{$cardHeight}' fill='white'/>
                <ellipse id='mask-ellipse' cx='{$currentStreakOffset}' cy='32' rx='13' ry='18' fill='black'/>
            </mask>
        </defs>
        <g clip-path='url(#outer_rectangle)'>
            <rect width='{$cardWidth}' height='{$cardHeight}' fill='{$theme['bg_color']}'/>
            <g stroke='{$theme['stroke']}' stroke-opacity='0.2'>
                <line x1='165' y1='28' x2='165' y2='170' />
                <line x1='330' y1='28' x2='330' y2='170' />
            </g>

            <g transform='translate(82.5, 48)' text-anchor='middle'>
                <text x='0' y='32' fill='{$theme['sideNums']}' class='stat' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>{$totalContributions}</text>
                <text x='0' y='64' fill='{$theme['sideLabels']}' class='label' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.7s'>Total Contributions</text>
                <text x='0' y='88' fill='{$theme['dates']}' class='date' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.8s'>{$totalRange}</text>
            </g>

            <g transform='translate({$currentStreakOffset}, 48)' text-anchor='middle'>
                <g mask='url(#mask_out_ring_behind_fire)'>
                    <circle cx='0' cy='23' r='40' fill='none' stroke='{$theme['ring']}' stroke-width='5' style='opacity: 0; animation: fadein 0.5s linear forwards 0.4s'></circle>
                </g>
                <g transform='translate(0, -28.5)' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>
                    <path d='M 1.5 0.67 C 1.5 0.67 2.24 3.32 2.24 5.47 C 2.24 7.53 0.89 9.2 -1.17 9.2 C -3.23 9.2 -4.79 7.53 -4.79 5.47 L -4.76 5.11 C -6.78 7.51 -8 10.62 -8 13.99 C -8 18.41 -4.42 22 0 22 C 4.42 22 8 18.41 8 13.99 C 8 8.6 5.41 3.79 1.5 0.67 Z M -0.29 19 C -2.07 19 -3.51 17.6 -3.51 15.86 C -3.51 14.24 -2.46 13.1 -0.7 12.74 C 1.07 12.38 2.9 11.53 3.92 10.16 C 4.31 11.45 4.51 12.81 4.51 14.2 C 4.51 16.85 2.36 19 -0.29 19 Z' fill='{$theme['fire']}'/>
                </g>
                <text x='0' y='32' fill='{$theme['currStreakNum']}' class='stat' font-size='28px' style='animation: currstreak 0.6s linear forwards'>{$currentStreak}</text>
                <text x='0' y='60' fill='{$theme['currStreakLabel']}' class='label' font-weight='700' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>Current Streak</text>
                <text x='0' y='80' fill='{$theme['dates']}' class='date' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>{$currentRange}</text>
            </g>

            <g transform='translate(412.5, 48)' text-anchor='middle'>
                <text x='0' y='32' fill='{$theme['sideNums']}' class='stat' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.2s'>{$longestStreak}</text>
                <text x='0' y='64' fill='{$theme['sideLabels']}' class='label' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.3s'>Longest Streak</text>
                <text x='0' y='88' fill='{$theme['dates']}' class='date' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.4s'>{$longestRange}</text>
            </g>
        </g>
    </svg>";
}

// --- 4. EJECUCIÓN ---
try {
    $data = getContributionGraphs($user, $token);
    $stats = getContributionStats($data['contributions']);
    file_put_contents("racha.svg", generateCard($stats));
    echo "Archivo racha.svg generado con éxito.\n";
} catch (Exception $e) {
    die("Error crítico: " . $e->getMessage() . "\n");
}
