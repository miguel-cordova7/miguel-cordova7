<?php
/**
 * GENERADOR DE RACHAS STANDALONE - FIDELIDAD TOTAL AL ORIGINAL
 * Integra la lógica completa de stats.php y card.php proporcionada.
 */

declare(strict_types=1);

$user = "miguel-cordova7";
$token = getenv('GH_TOKEN');

if (!$token) {
    die("Error: No se encontró el secreto GH_TOKEN en las variables de entorno.\n");
}

function getGitHubToken() { return getenv('GH_TOKEN'); }
function isWhitelisted($user) { return true; }

function buildContributionGraphQuery(string $user, int $year): string {
    $start = "$year-01-01T00:00:00Z";
    $end = "$year-12-31T23:59:59Z";
    return "query {
        user(login: \"$user\") {
            createdAt
            contributionsCollection(from: \"$start\", to: \"$end\") {
                contributionYears
                contributionCalendar {
                    weeks {
                        contributionDays {
                            contributionCount
                            date
                        }
                    }
                }
            }
        }
    }";
}

function getGraphQLCurlHandle(string $query, string $token): CurlHandle {
    $headers = [
        "Authorization: bearer $token",
        "Content-Type: application/json",
        "User-Agent: GitHub-Readme-Streak-Stats",
    ];
    $body = ["query" => $query];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/graphql");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    return $ch;
}

function executeContributionGraphRequests(string $user, array $years): array {
    $tokens = [];
    $requests = [];
    foreach ($years as $year) {
        $tokens[$year] = getGitHubToken();
        $query = buildContributionGraphQuery($user, $year);
        $requests[$year] = getGraphQLCurlHandle($query, $tokens[$year]);
    }
    $multi = curl_multi_init();
    foreach ($requests as $handle) {
        curl_multi_add_handle($multi, $handle);
    }
    $running = null;
    do {
        curl_multi_exec($multi, $running);
    } while ($running);
    $responses = [];
    foreach ($requests as $year => $handle) {
        $contents = curl_multi_getcontent($handle);
        $decoded = is_string($contents) ? json_decode($contents) : null;
        if (empty($decoded) || empty($decoded->data)) {
             continue;
        }
        $responses[$year] = $decoded;
        curl_multi_remove_handle($multi, $handle);
    }
    curl_multi_close($multi);
    return $responses;
}

function getContributionGraphs(string $user, ?int $startingYear = null): array {
    $currentYear = intval(date("Y"));
    $responses = executeContributionGraphRequests($user, [$currentYear]);
    $userCreatedDateTimeString = $responses[$currentYear]->data->user->createdAt ?? null;
    if (empty($userCreatedDateTimeString)) {
        throw new Exception("Error recuperando datos.");
    }
    $userCreatedYear = intval(explode("-", $userCreatedDateTimeString)[0]);
    $minimumYear = $startingYear ?: $userCreatedYear;
    $minimumYear = max($minimumYear, 2005);
    $yearsToRequest = range($minimumYear, $currentYear - 1);
    if (!empty($yearsToRequest)) {
        $responses += executeContributionGraphRequests($user, $yearsToRequest);
    }
    return $responses;
}

function getContributionDates(array $contributionGraphs): array {
    $contributions = [];
    $today = date("Y-m-d");
    $tomorrow = date("Y-m-d", strtotime("tomorrow"));
    ksort($contributionGraphs);
    foreach ($contributionGraphs as $graph) {
        $weeks = $graph->data->user->contributionsCollection->contributionCalendar->weeks;
        foreach ($weeks as $week) {
            foreach ($week->contributionDays as $day) {
                $date = $day->date;
                $count = $day->contributionCount;
                if ($date <= $today || ($date == $tomorrow && $count > 0)) {
                    $contributions[$date] = $count;
                }
            }
        }
    }
    return $contributions;
}

function getContributionStats(array $contributions, array $excludedDays = []): array {
    if (empty($contributions)) return [];
    $today = array_key_last($contributions);
    $first = array_key_first($contributions);
    $stats = [
        "mode" => "daily", "totalContributions" => 0, "firstContribution" => "",
        "longestStreak" => ["start" => $first, "end" => $first, "length" => 0],
        "currentStreak" => ["start" => $first, "end" => $first, "length" => 0],
        "excludedDays" => $excludedDays,
    ];
    foreach ($contributions as $date => $count) {
        $stats["totalContributions"] += $count;
        if ($count > 0) {
            ++$stats["currentStreak"]["length"];
            $stats["currentStreak"]["end"] = $date;
            if ($stats["currentStreak"]["length"] == 1) $stats["currentStreak"]["start"] = $date;
            if (!$stats["firstContribution"]) $stats["firstContribution"] = $date;
            if ($stats["currentStreak"]["length"] > $stats["longestStreak"]["length"]) {
                $stats["longestStreak"] = $stats["currentStreak"];
            }
        } elseif ($date != $today) {
            $stats["currentStreak"]["length"] = 0;
            $stats["currentStreak"]["start"] = $today;
            $stats["currentStreak"]["end"] = $today;
        }
    }
    return $stats;
}


function formatDate(string $dateString): string {
    $date = new DateTime($dateString);
    return $date->format(date("Y") == $date->format("Y") ? "M j" : "M j, Y");
}

function generateCard(array $stats): string {

    $theme = [
        "bg_color" => "#0d1117", "fire" => "#00a8f3", "ring" => "#00a8f3",
        "currStreakNum" => "#28a745", "currStreakLabel" => "#28a745",
        "sideNums" => "#00a8f3", "sideLabels" => "#fff", "dates" => "#768390",
        "stroke" => "#444", "border" => "#0000"
    ];

    $cardWidth = 495; $cardHeight = 195;
    $totalContributions = number_format($stats["totalContributions"]);
    $totalContributionsRange = formatDate($stats["firstContribution"]) . " - Present";
    $currentStreak = number_format($stats["currentStreak"]["length"]);
    $currentStreakRange = formatDate($stats["currentStreak"]["start"]) . ($stats["currentStreak"]["start"] != $stats["currentStreak"]["end"] ? " - " . formatDate($stats["currentStreak"]["end"]) : "");
    $longestStreak = number_format($stats["longestStreak"]["length"]);
    $longestStreakRange = formatDate($stats["longestStreak"]["start"]) . ($stats["longestStreak"]["start"] != $stats["longestStreak"]["end"] ? " - " . formatDate($stats["longestStreak"]["end"]) : "");

    return "
    <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$cardWidth} {$cardHeight}' width='{$cardWidth}px' height='{$cardHeight}px' style='isolation: isolate'>
        <style>
            @keyframes currstreak { 0% { font-size: 3px; opacity: 0.2; } 80% { font-size: 34px; opacity: 1; } 100% { font-size: 28px; opacity: 1; } }
            @keyframes fadein { 0% { opacity: 0; } 100% { opacity: 1; } }
        </style>
        <defs>
            <clipPath id='outer_rectangle'><rect width='{$cardWidth}' height='{$cardHeight}' rx='4.5'/></clipPath>
            <mask id='mask_out_ring_behind_fire'>
                <rect width='{$cardWidth}' height='{$cardHeight}' fill='white'/>
                <ellipse cx='247.5' cy='32' rx='13' ry='18' fill='black'/>
            </mask>
        </defs>
        <g clip-path='url(#outer_rectangle)'>
            <rect fill='{$theme["bg_color"]}' width='{$cardWidth}' height='{$cardHeight}'/>
            <g stroke='{$theme["stroke"]}' stroke-opacity='0.2'>
                <line x1='165' y1='28' x2='165' y2='170' />
                <line x1='330' y1='28' x2='330' y2='170' />
            </g>
            <g transform='translate(82.5, 48)' text-anchor='middle'>
                <text x='0' y='32' fill='{$theme["sideNums"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>{$totalContributions}</text>
                <text x='0' y='64' fill='{$theme["sideLabels"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.7s'>Total Contributions</text>
                <text x='0' y='88' fill='{$theme["dates"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.8s'>{$totalContributionsRange}</text>
            </g>
            <g transform='translate(247.5, 48)' text-anchor='middle'>
                <g mask='url(#mask_out_ring_behind_fire)'>
                    <circle cx='0' cy='23' r='40' fill='none' stroke='{$theme["ring"]}' stroke-width='5' style='opacity: 0; animation: fadein 0.5s linear forwards 0.4s'></circle>
                </g>
                <g transform='translate(0, -28.5)' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>
                    <path d='M 1.5 0.67 C 1.5 0.67 2.24 3.32 2.24 5.47 C 2.24 7.53 0.89 9.2 -1.17 9.2 C -3.23 9.2 -4.79 7.53 -4.79 5.47 L -4.76 5.11 C -6.78 7.51 -8 10.62 -8 13.99 C -8 18.41 -4.42 22 0 22 C 4.42 22 8 18.41 8 13.99 C 8 8.6 5.41 3.79 1.5 0.67 Z M -0.29 19 C -2.07 19 -3.51 17.6 -3.51 15.86 C -3.51 14.24 -2.46 13.1 -0.7 12.74 C 1.07 12.38 2.9 11.53 3.92 10.16 C 4.31 11.45 4.51 12.81 4.51 14.2 C 4.51 16.85 2.36 19 -0.29 19 Z' fill='{$theme["fire"]}'/>
                </g>
                <text x='0' y='32' fill='{$theme["currStreakNum"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='28px' style='animation: currstreak 0.6s linear forwards'>{$currentStreak}</text>
                <text x='0' y='60' fill='{$theme["currStreakLabel"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>Current Streak</text>
                <text x='0' y='80' fill='{$theme["dates"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>{$currentStreakRange}</text>
            </g>
            <g transform='translate(412.5, 48)' text-anchor='middle'>
                <text x='0' y='32' fill='{$theme["sideNums"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-weight='700' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.2s'>{$longestStreak}</text>
                <text x='0' y='64' fill='{$theme["sideLabels"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='14px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.3s'>Longest Streak</text>
                <text x='0' y='88' fill='{$theme["dates"]}' font-family='\"Segoe UI\", Ubuntu, sans-serif' font-size='12px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.4s'>{$longestStreakRange}</text>
            </g>
        </g>
    </svg>";
}


try {
    echo "Obteniendo datos para $user...\n";
    $graphs = getContributionGraphs($user);
    $dates = getContributionDates($graphs);
    $stats = getContributionStats($dates);
    $svg = generateCard($stats);
    file_put_contents("racha.svg", $svg);
    echo "SVG generado correctamente en racha.svg\n";
} catch (Exception $e) {
    die("Error crítico: " . $e->getMessage() . "\n");
}
