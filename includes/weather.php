<?php
declare(strict_types=1);

function thika_weather(): string
{
    $cache = __DIR__ . '/../cache/weather.json';
    if (is_file($cache) && time() - filemtime($cache) < 1800) {
        $saved = json_decode((string) file_get_contents($cache), true);
        if (is_array($saved) && isset($saved['label'])) {
            return (string) $saved['label'];
        }
    }

    $url = 'https://api.open-meteo.com/v1/forecast?latitude=-1.0333&longitude=37.0693&current=temperature_2m&timezone=Africa%2FNairobi';
    $context = stream_context_create(['http' => ['timeout' => 3]]);
    $raw = @file_get_contents($url, false, $context);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $temp = is_array($data) ? ($data['current']['temperature_2m'] ?? null) : null;
    if (!is_numeric($temp)) {
        return 'Thika';
    }

    $label = (int) round((float) $temp) . '° Thika';
    if (!is_dir(dirname($cache))) {
        @mkdir(dirname($cache), 0777, true);
    }
    @file_put_contents($cache, json_encode(['label' => $label]));

    return $label;
}
