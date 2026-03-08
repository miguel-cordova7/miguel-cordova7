<?php


declare(strict_types=1);

// 1. Configuración de entorno requerida por los scripts originales
$user = "miguel-cordova7";
$_SERVER["TOKEN"] = getenv('GH_TOKEN'); // stats.php busca esto
$_SERVER["WHITELIST"] = $user;         // whitelist.php busca esto

// 2. Parámetros de personalización (como los que eliges en la web del creador)
// Esto asegura que el tema sea transparente y sin bordes
$_REQUEST["theme"] = "transparent"; 
$_REQUEST["hide_border"] = "true";
$_REQUEST["border_radius"] = "7.4";

// 3. Cargar el motor original desde la carpeta src/
// Cambiamos al directorio src para que los "include" relativos funcionen
set_include_path(__DIR__ . '/src');
require_once "stats.php";
require_once "card.php";

try {
    echo "Iniciando motor original para $user...\n";

    // Lógica exacta de stats.php
    $contributionGraphs = getContributionGraphs($user);
    $contributions = getContributionDates($contributionGraphs);
    $stats = getContributionStats($contributions);

    // Lógica exacta de card.php para generar el SVG
    $svg = generateCard($stats, $_REQUEST);

    // Guardar el resultado idéntico en racha.svg
    file_put_contents(__DIR__ . "/racha.svg", $svg);
    
    echo "¡Éxito! racha.svg generado con el diseño original completo.\n";

} catch (Exception $e) {
    die("Error en el motor original: " . $e->getMessage() . "\n");
}
