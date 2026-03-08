<?php
/**
 * CONTROLADOR DE RACHAS
 */

declare(strict_types=1);

// 1. Configuración de entorno
$user = "miguel-cordova7";
$_SERVER["TOKEN"] = getenv('GH_TOKEN');
$_SERVER["WHITELIST"] = $user;

// 2. Parámetros de personalización
$_REQUEST["theme"] = "transparent";
$_REQUEST["hide_border"] = "true";
$_REQUEST["border_radius"] = "7.4";

$colorCeleste = "00AEFF";
$colorVerde = "28a745";

$_REQUEST["sideNums"] = $colorCeleste; 

$_REQUEST["ring"] = $colorCeleste;

$_REQUEST["fire"] = $colorCeleste;

$_REQUEST["currStreakNum"] = $colorVerde;

$_REQUEST["sideLabels"] = "FFFFFF";
$_REQUEST["currStreakLabel"] = "FFFFFF";

$_REQUEST["dates"] = "768390";

// 3. Cargar el motor desde la carpeta src/
set_include_path(__DIR__ . '/src');
require_once "stats.php";
require_once "card.php";

try {
    echo "Iniciando motor original para $user con colores personalizados...\n";

    $contributionGraphs = getContributionGraphs($user);
    $contributions = getContributionDates($contributionGraphs);
    $stats = getContributionStats($contributions);

    $svg = generateCard($stats, $_REQUEST);

    file_put_contents(__DIR__ . "/racha.svg", $svg);
    
    echo "¡Éxito! racha.svg generado con el diseño original y colores celestes.\n";

} catch (Exception $e) {
    die("Error en el motor original: " . $e->getMessage() . "\n");
}
