<?php
/** * MOTOR DE RACHAS PREMIUM - CLON FIEL DEL ORIGINAL
 * Basado estrictamente en los archivos stats.php y card.php proporcionados
 */

$user = "miguel-cordova7";
$token = getenv('GH_TOKEN');

if (!$token) die("Error: GH_TOKEN no configurado.");

function get_github_data($user, $token) {
    $query = 'query { user(login: "'.$user.'") { contributionsCollection { contributionCalendar { totalContributions weeks { contributionDays { contributionCount date } } } } } }';
    $ch = curl_init("https://api.github.com/graphql");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["Authorization: bearer $token", "Content-Type: application/json", "User-Agent: Streak-Stats"],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["query" => $query]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    return json_decode(curl_exec($ch), true);
}

$response = get_github_data($user, $token);
$calendar = $response['data']['user']['contributionsCollection']['contributionCalendar'];
$total = number_format($calendar['totalContributions']);

$days = [];
foreach ($calendar['weeks'] as $week) {
    foreach ($week['contributionDays'] as $day) {
        $days[$day['date']] = $day['contributionCount'];
    }
}

$currentStreak = 0; $longestStreak = 0; $tempStreak = 0;
$today = date("Y-m-d"); $firstDay = array_key_first($days);

foreach ($days as $date => $count) {
    if ($count > 0) {
        $tempStreak++;
        if ($tempStreak > $longestStreak) $longestStreak = $tempStreak;
    } else { $tempStreak = 0; }
}

$reversed = array_reverse($days);
$found = false;
foreach ($reversed as $date => $count) {
    if ($date == $today && $count == 0) continue;
    if ($count > 0) { $currentStreak++; $found = true; }
    elseif ($found) break;
}

$startDate = date("M j, Y", strtotime($firstDay));
$currDateFormatted = date("M j");


$theme = [
    "bg" => "#0d1117",
    "fire" => "#00a8f3",
    "ring" => "#00a8f3",
    "currNum" => "#28a745",
    "currLabel" => "#28a745",
    "sideNums" => "#00a8f3",
    "sideLabels" => "#fff",
    "dates" => "#768390"
];

$svg = "
<svg xmlns='http://www.w3.org/2000/svg' width='495' height='195' viewBox='0 0 495 195' style='isolation: isolate'>
    <style>
        @keyframes currstreak { 0% { font-size: 3px; opacity: 0.2; } 80% { font-size: 34px; opacity: 1; } 100% { font-size: 28px; opacity: 1; } }
        @keyframes fadein { 0% { opacity: 0; } 100% { opacity: 1; } }
        .stat { font-family: 'Segoe UI', Ubuntu, sans-serif; font-weight: 700; }
        .label { font-family: 'Segoe UI', Ubuntu, sans-serif; font-weight: 400; font-size: 14px; }
        .date { font-family: 'Segoe UI', Ubuntu, sans-serif; font-weight: 400; font-size: 12px; }
    </style>
    <defs>
        <mask id='mask_out_ring_behind_fire'>
            <rect width='495' height='195' fill='white'/>
            <ellipse cx='247.5' cy='32' rx='13' ry='18' fill='black'/>
        </mask>
    </defs>
    
    <rect width='494' height='194' x='0.5' y='0.5' fill='{$theme['bg']}' rx='4.5' stroke='none'/>

    <g stroke='#E4E2E2' stroke-opacity='0.2'>
        <line x1='165' y1='28' x2='165' y2='170' />
        <line x1='330' y1='28' x2='330' y2='170' />
    </g>

    <g transform='translate(82.5, 48)' text-anchor='middle'>
        <text x='0' y='32' fill='{$theme['sideNums']}' class='stat' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>$total</text>
        <text x='0' y='64' fill='{$theme['sideLabels']}' class='label' style='opacity: 0; animation: fadein 0.5s linear forwards 0.7s'>Total Contributions</text>
        <text x='0' y='88' fill='{$theme['dates']}' class='date' style='opacity: 0; animation: fadein 0.5s linear forwards 0.8s'>$startDate - Present</text>
    </g>

    <g transform='translate(247.5, 0)' text-anchor='middle'>
        <g mask='url(#mask_out_ring_behind_fire)'>
            <circle cx='0' cy='71' r='40' fill='none' stroke='{$theme['ring']}' stroke-width='5' style='opacity: 0; animation: fadein 0.5s linear forwards 0.4s'/>
        </g>
        <g transform='translate(0, 19.5)' style='opacity: 0; animation: fadein 0.5s linear forwards 0.6s'>
            <path d='M 1.5 0.67 C 1.5 0.67 2.24 3.32 2.24 5.47 C 2.24 7.53 0.89 9.2 -1.17 9.2 C -3.23 9.2 -4.79 7.53 -4.79 5.47 L -4.76 5.11 C -6.78 7.51 -8 10.62 -8 13.99 C -8 18.41 -4.42 22 0 22 C 4.42 22 8 18.41 8 13.99 C 8 8.6 5.41 3.79 1.5 0.67 Z M -0.29 19 C -2.07 19 -3.51 17.6 -3.51 15.86 C -3.51 14.24 -2.46 13.1 -0.7 12.74 C 1.07 12.38 2.9 11.53 3.92 10.16 C 4.31 11.45 4.51 12.81 4.51 14.2 C 4.51 16.85 2.36 19 -0.29 19 Z' fill='{$theme['fire']}'/>
        </g>
        <text x='0' y='80' fill='{$theme['currNum']}' class='stat' font-size='28px' style='animation: currstreak 0.6s linear forwards'>$currentStreak</text>
        <text x='0' y='108' fill='{$theme['currLabel']}' class='label' font-weight='700' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>Current Streak</text>
        <text x='0' y='130' fill='{$theme['dates']}' class='date' style='opacity: 0; animation: fadein 0.5s linear forwards 0.9s'>$currDateFormatted</text>
    </g>

    <g transform='translate(412.5, 48)' text-anchor='middle'>
        <text x='0' y='32' fill='{$theme['sideNums']}' class='stat' font-size='28px' style='opacity: 0; animation: fadein 0.5s linear forwards 1.2s'>$longestStreak</text>
        <text x='0' y='64' fill='{$theme['sideLabels']}' class='label' style='opacity: 0; animation: fadein 0.5s linear forwards 1.3s'>Longest Streak</text>
        <text x='0' y='88' fill='{$theme['dates']}' class='date' style='opacity: 0; animation: fadein 0.5s linear forwards 1.4s'>Estadística Histórica</text>
    </g>
</svg>";

file_put_contents("racha.svg", $svg);
echo "SVG Premium generado.\n";
