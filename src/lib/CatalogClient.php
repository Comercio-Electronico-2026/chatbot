<?php

declare(strict_types=1);

final class CatalogClient
{
    private const BASE_URL = 'https://fakestoreapi.com';
    private const ES_EN = [
        'teclado' => 'keyboard',
        'pantalla' => 'monitor',
        'monitor' => 'monitor',
        'computadora' => 'computer',
        'portatil' => 'laptop',
        'zapatos' => 'shoes',
        'camisa' => 'shirt',
        'camiseta' => 'shirt',
        'ropa' => 'clothing',
        'blusa' => 'blouse',
        'vestido' => 'dress',
        'chaqueta' => 'jacket',
        'joya' => 'jewelery',
        'joyas' => 'jewelery',
        'joyeria' => 'jewelery',
        'anillo' => 'ring',
        'collar' => 'necklace',
        'aretes' => 'earrings',
        'auriculares' => 'headphones',
        'audifonos' => 'headphones',
        'reloj' => 'watch',
        'bolso' => 'bag',
        'disco' => 'hard drive',
        'memoria' => 'ssd',
        'tarjeta' => 'card',
        ' Cable' => 'cable',
        'cable' => 'cable',
        'bateria' => 'battery',
        'lentes' => 'glasses',
        'sombrero' => 'hat',
    ];

    public function categories(): ?array
    {
        return $this->http('/products/categories');
    }

    public function byCategory(string $category): ?array
    {
        return $this->http('/products/category/' . rawurlencode($category));
    }

    public function search(string $query): ?array
    {
        $products = $this->http('/products');
        if ($products === null) {
            return null;
        }
        $needle = $this->normalize($query);
        $matches = $this->filter($products, $needle);
        if ($matches === []) {
            $alt = $this->translate($needle);
            if ($alt !== null) {
                $matches = $this->filter($products, $alt);
            }
        }
        return $matches;
    }

    private function filter(array $products, string $needle): array
    {
        $matches = [];
        foreach ($products as $product) {
            $haystack = $this->normalize((string) ($product['title'] ?? '') . ' ' . (string) ($product['category'] ?? ''));
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                $matches[] = $product;
            }
        }
        return $matches;
    }

    private function translate(string $needle): ?string
    {
        $words = preg_split('/\s+/u', $needle) ?: [];
        $translated = [];
        foreach ($words as $word) {
            $translated[] = self::ES_EN[$word] ?? $word;
        }
        $alt = trim(implode(' ', $translated));
        return $alt !== $needle && $alt !== '' ? $alt : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
    }

    private function http(string $path): ?array
    {
        $ch = curl_init(self::BASE_URL . $path);
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
            Log::error('catalogo', $error ?: 'fallo de conexion');
            return null;
        }
        if ($status >= 400) {
            Log::error('catalogo', 'HTTP ' . $status);
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
