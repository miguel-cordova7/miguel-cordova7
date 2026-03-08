<?php
/**
 * GENERADOR DE RACHAS PERSONAL (Versión PHP)
 * Basado en DenverCoder1/github-readme-streak-stats
 */

$user = "miguel-cordova7";
$token = getenv('GH_TOKEN');

if (!$token) {
    die("Error: No se encontró el secreto GH_TOKEN.\n");
}

function get_contributions($user, $token) {
    $query = 'query {
        user(login: "' . $user . '") {
            contributionsCollection {
                contributionCalendar {
                    totalContributions
                    weeks {
                        contributionDays {
                            contributionCount
                            date
                        }
                    }
                }
            }
        }
    }';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/graphql");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: bearer $token",
        "Content-Type: application/json",
        "User-Agent: PHP-Streak-Script"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["query" => $query]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

$data = get_contributions($user, $token);
$calendar = $data['data']['user']['contributionsCollection']['contributionCalendar'];
$total = $calendar['totalContributions'];

$days = [];
foreach ($calendar['weeks'] as $week) {
    foreach ($week['contributionDays'] as $day) {
        $days[$day['date']] = $day['contributionCount'];
    }
}

// Cálculo de rachas
$currentStreak = 0;
$longestStreak = 0;
$tempStreak = 0;
$today = date("Y-m-d");

foreach ($days as $date => $count) {
    if ($count > 0) {
        $tempStreak++;
        if ($tempStreak > $longestStreak) $longestStreak = $tempStreak;
    } else {
        $tempStreak = 0;
    }
}

// Racha actual (hacia atrás)
$reversedDays = array_reverse($days);
$foundCurrent = false;
foreach ($reversedDays as $date => $count) {
    if ($date == $today && $count == 0) continue;
    if ($count > 0) {
        $currentStreak++;
        $foundCurrent = true;
    } elseif ($foundCurrent) {
        break;
    }
}

// Formatear fechas para el diseño
$startDate = date("M j, Y", strtotime(array_key_first($days)));
$currentDate = date("M j");

$svg = "
<svg xmlns='http://www.w3.org/2000/svg' width='495' height='195' viewBox='0 0 495 195'>
    <style>
        .stat { font: 700 32px 'Segoe UI', Ubuntu, sans-serif; fill: #00a8f3; }
        .stat-curr { font: 700 28px 'Segoe UI', Ubuntu, sans-serif; fill: #28a745; }
        .label { font: 600 14px 'Segoe UI', Ubuntu, sans-serif; fill: #00a8f3; }
        .label-curr { font: 700 14px 'Segoe UI', Ubuntu, sans-serif; fill: #28a745; }
        .date { font: 400 12px 'Segoe UI', Ubuntu, sans-serif; fill: #768390; }
        @keyframes fadein { from { opacity: 0; } to { opacity: 1; } }
    </style>

    <rect width='495' height='195' fill='#0d1117' rx='4.5'/>

    <g stroke='#E4E2E2' stroke-opacity='0.2' stroke-width='1.5'>
        <line x1='165' y1='45' x2='165' y2='155' />
        <line x1='330' y1='45' x2='330' y2='155' />
    </g>

    <g text-anchor='middle' style='animation: fadein 0.8s ease-in-out'>
        <text x='82.5' y='95' class='stat'>$total</text>
        <text x='82.5' y='130' class='label'>Total Contributions</text>
        <text x='82.5' y='155' class='date'>$startDate - Present</text>
    </g>

    <g text-anchor='middle'>
        <path d='M 225.5 51.2 A 42 42 0 1 0 269.5 51.2' fill='none' stroke='#00a8f3' stroke-width='4.5' stroke-linecap='round' />
        
        <g transform='translate(233, 24) scale(1.2)'>
            <path fill='#00a8f3' d='M11.5 22.04c-1.35 0-2.62-.52-3.57-1.45-.96-.93-1.48-2.22-1.46-3.57.03-2.12 1.13-3.9 2.1-5.47.41-.66.81-1.31 1.15-2.03.4-1.03.51-2.13.36-3.26-.01-.04-.03-.07-.05-.1.18-.2.43-.3.7-.3.26 0 .51.1.7.3.16.35.33.7.53 1.05.5 1.05 1.1 2.15 1.83 3.06.75.95 1.63 1.82 2.45 2.77 1.05 1.25 1.7 2.8 1.68 4.4-.01 1.4-.55 2.72-1.53 3.67-.98.98-2.31 1.52-3.71 1.52zm-1.63-4.5c.23.25.55.38.88.38.33 0 .65-.13.88-.38.21-.23.33-.53.31-.85 0-.25-.08-.48-.23-.67l-1.36-1.58c-.38-.43-.61-.98-.68-1.55-.03-.55.1-1.08.35-1.55l.06-.13c-.23.37-.45.75-.66 1.12-.38.63-.78 1.3-1.15 2.02-.18.35-.35.72-.48 1.12-.18.58-.25 1.18-.16 1.78.08.52.31.98.66 1.37z' />
        </g>

        <text x='247.5' y='105' class='stat-curr'>$currentStreak</text>
        <text x='247.5' y='145' class='label-curr'>Current Streak</text>
        <text x='247.5' y='165' class='date'>$currentDate</text>
    </g>

    <g text-anchor='middle' style='animation: fadein 0.8s ease-in-out'>
        <text x='412.5' y='95' class='stat'>$longestStreak</text>
        <text x='412.5' y='130' class='label'>Longest Streak</text>
        <text x='412.5' y='155' class='date'>Estadística Histórica</text>
    </g>
</svg>";

file_put_contents("racha.svg", $svg);
echo "SVG generado con éxito en PHP.\n";
