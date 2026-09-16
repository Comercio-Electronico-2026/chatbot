<?php

declare(strict_types=1);

final class OpenMeteoClient
{
    private const GEO_URL = 'https://geocoding-api.open-meteo.com/v1/search';
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    public function weather(string $city): array
    {
        $geo = $this->http(self::GEO_URL, [
            'name' => $city,
            'count' => 1,
            'language' => 'es',
            'format' => 'json',
        ]);
        if ($geo === null) {
            return self::failure();
        }
        if (empty($geo['results'][0])) {
            return ['ok' => false, 'reason' => 'city_not_found'];
        }
        $place = $geo['results'][0];
        $current = $this->current((float) $place['latitude'], (float) $place['longitude']);
        if ($current === null) {
            return self::failure();
        }
        return $current + [
            'city' => (string) ($place['name'] ?? $city),
            'country' => (string) ($place['country'] ?? ''),
        ];
    }

    public function byCoords(float $latitude, float $longitude): array
    {
        $current = $this->current($latitude, $longitude);
        if ($current === null) {
            return self::failure();
        }
        return $current + [
            'city' => 'tu ubicación',
            'country' => '',
        ];
    }

    public static function describe(int $code): string
    {
        return match (true) {
            $code === 0 => 'cielo despejado',
            $code <= 2 => 'parcialmente nublado',
            $code === 3 => 'nublado',
            $code <= 48 => 'con neblina',
            $code <= 57 => 'con llovizna',
            $code <= 67 => 'con lluvia',
            $code <= 77 => 'con nieve',
            $code <= 82 => 'con chubascos de lluvia',
            $code <= 86 => 'con chubascos de nieve',
            default => 'con tormenta',
        };
    }

    private static function failure(): array
    {
        return ['ok' => false, 'reason' => 'api_down'];
    }

    private function current(float $latitude, float $longitude): ?array
    {
        $data = $this->http(self::FORECAST_URL, [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,apparent_temperature,weather_code',
            'timezone' => 'auto',
        ]);
        if ($data === null || !isset($data['current']['temperature_2m'])) {
            return null;
        }
        return [
            'ok' => true,
            'temperature' => (float) $data['current']['temperature_2m'],
            'feels' => (float) ($data['current']['apparent_temperature'] ?? $data['current']['temperature_2m']),
            'code' => (int) ($data['current']['weather_code'] ?? 0),
        ];
    }

    private function http(string $url, array $query): ?array
    {
        $ch = curl_init($url . '?' . http_build_query($query));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $error = $raw === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($raw === false) {
            Log::error('openmeteo', $error ?: 'fallo de conexion');
            return null;
        }
        if ($status >= 400) {
            Log::error('openmeteo', 'HTTP ' . $status);
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
