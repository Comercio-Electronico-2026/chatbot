<?php

declare(strict_types=1);

class Conversation
{
    public function __construct(private readonly Closure $catalog, private readonly Closure $openAi, private readonly string $supportUrl)
    {
        if (parse_url($supportUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('SUPPORT_URL debe usar HTTPS.');
        }
    }

    public static function menuButtons(): array
    {
        return [
            [['text' => 'Ver catálogo', 'callback_data' => 'catalog'], ['text' => 'Categorías', 'callback_data' => 'categories']],
            [['text' => 'Buscar producto', 'callback_data' => 'search'], ['text' => 'Ofertas', 'callback_data' => 'offers']],
            [['text' => 'Ayuda', 'callback_data' => 'help'], ['text' => 'Atención de la tienda', 'callback_data' => 'support']],
        ];
    }

    private function reply(string $text, ?array $buttons = null): array
    {
        return ['text' => $text, 'reply_markup' => ['inline_keyboard' => $buttons ?? self::menuButtons()]];
    }

    private function menu(array &$state, string $prefix = ''): array
    {
        $state = ['step' => 'menu', 'attempts' => 0];
        return $this->reply($prefix . "¿Qué deseas consultar?\n1. Ver catálogo\n2. Ver categorías\n3. Buscar producto\n4. Ofertas\n5. Ayuda\n6. Atención de la tienda");
    }

    private function support(array &$state, string $prefix = ''): array
    {
        $state = ['step' => 'menu', 'attempts' => 0];
        return $this->reply($prefix . 'Para continuar con atención de la tienda, visita su sitio. Este bot no conecta automáticamente con un operador.', [
            [['text' => 'Visitar sitio de atención', 'url' => $this->supportUrl]],
            [['text' => 'Volver al menú', 'callback_data' => 'menu']],
        ]);
    }

    private function invalid(array &$state, string $reason): array
    {
        $state['attempts'] = ($state['attempts'] ?? 0) + 1;
        if ($state['attempts'] >= 3) {
            return $this->support($state, "Llegamos a tres intentos sin resolver la consulta. Cerré la operación actual.\n\n");
        }
        return $this->reply($reason . "\nIntento {$state['attempts']} de 3. Usa los botones, /ayuda o /cancelar.");
    }

    private function listProducts(array $products, array &$state, string $title): array
    {
        $state = ['step' => 'select', 'attempts' => 0, 'choices' => array_column($products, 'id')];
        if ($products === []) {
            return $this->menu($state, "No hay productos para esta consulta.\n\n");
        }
        $lines = [$title];
        $buttons = [];
        foreach ($products as $index => $product) {
            $lines[] = ($index + 1) . '. ' . $product['name'] . ' — ' . $product['price']
                . ($product['on_sale'] ? ' (oferta; antes ' . $product['regular_price'] . ')' : '');
            $buttons[] = [['text' => mb_substr($product['name'], 0, 60), 'callback_data' => 'product:' . $product['id']]];
        }
        $buttons[] = [['text' => 'Menú', 'callback_data' => 'menu'], ['text' => 'Cancelar', 'callback_data' => 'cancel']];
        return $this->reply(implode("\n", $lines) . "\n\nSelecciona un producto o escribe su número para ver sus datos.", $buttons);
    }

    private function showProduct(array $product, array &$state): array
    {
        $state = ['step' => 'product', 'attempts' => 0, 'selected' => $product['id']];
        $text = $product['name'] . "\nCategoría: " . implode(', ', $product['categories']) . "\nPrecio actual: " . $product['price'];
        if ($product['on_sale']) {
            $text .= "\nPrecio anterior: " . $product['regular_price'] . "\nEstado: oferta";
        }
        return $this->reply($text . "\n\n¿Deseas abrir el producto en la tienda para continuar la compra?", [
            [['text' => 'Sí, ver enlace', 'callback_data' => 'buy:' . $product['id']], ['text' => 'No', 'callback_data' => 'more']],
            [['text' => 'Menú', 'callback_data' => 'menu'], ['text' => 'Cancelar', 'callback_data' => 'cancel']],
        ]);
    }

    private function purchase(array $product, array &$state): array
    {
        if (parse_url($product['url'], PHP_URL_SCHEME) !== 'https'
            || parse_url($product['url'], PHP_URL_HOST) !== parse_url(setting('STORE_API_URL', 'https://mt23014.duckdns.org/'), PHP_URL_HOST)) {
            throw new ServiceFailure('WooCommerce');
        }
        $state = ['step' => 'more', 'attempts' => 0];
        return $this->reply('Continúa la compra en la página de ' . $product['name'] . ".\n" . $product['url']
            . "\n\n¿Necesitas consultar otro producto?", [
                [['text' => 'Abrir producto en la tienda', 'url' => $product['url']]],
                [['text' => 'Sí, volver al menú', 'callback_data' => 'menu'], ['text' => 'No, terminar', 'callback_data' => 'finish']],
            ]);
    }

    private function categories(array $products, array &$state): array
    {
        $names = [];
        foreach ($products as $product) {
            $names = array_merge($names, $product['categories']);
        }
        $names = array_values(array_unique($names));
        $state = ['step' => 'category', 'attempts' => 0, 'category_choices' => $names];
        $lines = ['Elige una categoría:'];
        $buttons = [];
        foreach ($names as $index => $name) {
            $lines[] = ($index + 1) . '. ' . $name;
            $buttons[] = [['text' => $name, 'callback_data' => 'category:' . $index]];
        }
        $buttons[] = [['text' => 'Menú', 'callback_data' => 'menu'], ['text' => 'Cancelar', 'callback_data' => 'cancel']];
        return $this->reply(implode("\n", $lines), $buttons);
    }

    private function matchingProducts(string $text, array $products): array
    {
        $query = normalText($text);
        preg_match_all('/[a-z]*\d+[a-z]*/', $query, $numbers);
        $terms = array_values(array_diff(explode(' ', $query), ['el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'en', 'por', 'que', 'cuanto',
            'cuesta', 'cuestan', 'precio', 'quiero', 'ver', 'comprar', 'buscar', '/buscar', 'producto', 'muestrame', 'tienen']));
        return array_values(array_filter($products, static function (array $product) use ($query, $numbers, $terms): bool {
            $name = normalText($product['name']);
            if ($numbers[0] !== []) {
                foreach ($numbers[0] as $number) {
                    if (!preg_match('/\b' . preg_quote($number, '/') . '\b/', $name)) {
                        return false;
                    }
                }
                return true;
            }
            $allTerms = $terms !== [];
            foreach ($terms as $term) {
                if (!str_contains($name, $term)) {
                    $allTerms = false;
                }
            }
            return $query !== '' && ($allTerms || str_contains($query, $name) || str_contains($name, $query)
                || (str_contains($query, 'dgx') && str_contains($name, 'dgx'))
                || (str_contains($query, 'poweredge') && str_contains($name, 'poweredge')));
        }));
    }

    public function handle(string $text, ?string $callback, bool $supported, array &$state): array
    {
        $state += ['step' => 'menu', 'attempts' => 0];
        $query = normalText($text);
        $command = strtolower(preg_replace('/@[A-Za-z0-9_]+/', '', explode(' ', trim($text))[0]) ?? '');
        $commands = ['/start' => 'start', '/menu' => 'menu', '/ayuda' => 'help', '/help' => 'help', '/cancelar' => 'cancel',
            '/volver' => 'menu', '/soporte' => 'support', '/catalogo' => 'catalog', '/categorias' => 'categories', '/ofertas' => 'offers'];
        $words = ['hola' => 'start', 'menu' => 'menu', 'volver' => 'menu', 'atras' => 'menu', 'ayuda' => 'help', 'necesito ayuda' => 'help',
            'cancelar' => 'cancel', 'ya no quiero continuar' => 'cancel', 'soporte' => 'support', 'hablar con un humano' => 'support',
            'ver catalogo' => 'catalog', 'catalogo' => 'catalog', 'que productos tienen' => 'catalog', 'muestrame el catalogo' => 'catalog',
            'ver categorias' => 'categories', 'categorias' => 'categories', 'buscar producto' => 'search', 'buscar un producto' => 'search',
            'ofertas' => 'offers', 'ver ofertas' => 'offers', 'que productos estan en oferta' => 'offers', 'cuales productos estan en oferta' => 'offers'];
        $action = $callback ?? $commands[$command] ?? $words[$query] ?? null;
        if ($state['step'] === 'menu' && ctype_digit($query) && $callback === null) {
            $action = [1 => 'catalog', 2 => 'categories', 3 => 'search', 4 => 'offers', 5 => 'help', 6 => 'support'][(int) $query] ?? null;
        }
        // Las interrupciones globales no dependen del estado ni de las APIs.
        if ($action === 'start') {
            return $this->menu($state, "¡Hola! Bienvenido a Tienda Electrónica.\nConsulta el catálogo, categorías, precios y ofertas; abre los productos para comprar en la web. No proceso pagos ni pedidos.\nLas consultas abiertas usan OpenAI; no compartas datos personales.\n\n");
        }
        if ($action === 'menu' || ($state['step'] === 'more' && $query === 'si')) {
            return $this->menu($state);
        }
        if ($action === 'cancel') {
            return $this->menu($state, "Operación cancelada.\n\n");
        }
        if ($action === 'help') {
            return $this->reply("Consulta catálogo, categorías, precios y ofertas. Ejemplo: ¿Cuánto cuesta la RTX 5090?\nTambién puedes preguntar sobre estos productos.\n/menu vuelve al inicio; /cancelar abandona la operación; /soporte abre el sitio de atención.");
        }
        if ($action === 'support') {
            return $this->support($state);
        }
        if ($action === 'finish' || ($state['step'] === 'more' && in_array($query, ['no', 'gracias', 'no gracias'], true))) {
            return $this->menu($state, "Gracias por visitar Tienda Electrónica. Puedes consultar nuevamente cuando quieras.\n\n");
        }
        if ($action === 'more' || ($state['step'] === 'product' && $query === 'no')) {
            $state = ['step' => 'more', 'attempts' => 0];
            return $this->reply('¿Necesitas consultar otro producto?', [[['text' => 'Sí', 'callback_data' => 'menu'], ['text' => 'No', 'callback_data' => 'finish']]]);
        }
        if (!$supported || ($callback === null && trim($text) === '')) {
            return $this->invalid($state, 'Por ahora comprendo texto y botones. Escribe tu consulta o usa el menú.');
        }
        if ($callback === null && str_starts_with($command, '/') && !isset($commands[$command]) && $command !== '/buscar') {
            return $this->invalid($state, 'No reconozco ese comando.');
        }
        if (mb_strlen($text) > 1500 || containsPersonalData($text)) {
            return $this->reply('Escribe una consulta breve sobre productos, sin datos personales. Puedes usar /menu.');
        }
        if ($action === 'search' || ($command === '/buscar' && !str_contains(trim($text), ' '))) {
            $state = ['step' => 'search', 'attempts' => 0];
            return $this->reply('¿Qué producto deseas consultar? Escribe su nombre; por ejemplo, RTX 5090.');
        }
        try {
            $products = ($this->catalog)();
            if ($action === 'catalog') {
                return $this->listProducts($products, $state, 'Este es el catálogo actual:');
            }
            if ($action === 'offers' || ($callback === null && preg_match('/\bofertas?\b/', $query))) {
                return $this->listProducts(array_values(array_filter($products, static fn(array $p): bool => $p['on_sale'])), $state, 'Productos en oferta:');
            }
            if ($action === 'categories') {
                return $this->categories($products, $state);
            }
            $category = null;
            if ($callback !== null && preg_match('/^category:(\d+)$/', $callback, $match)) {
                $category = $state['category_choices'][(int) $match[1]] ?? null;
                if ($category === null) {
                    return $this->invalid($state, 'Esa categoría ya no forma parte de la selección. Abre Categorías nuevamente.');
                }
            } elseif ($state['step'] === 'category' && ctype_digit($query)) {
                $category = $state['category_choices'][(int) $query - 1] ?? null;
            } else {
                foreach ($products as $product) {
                    foreach ($product['categories'] as $name) {
                        $normalized = normalText($name);
                        if (str_contains($query, $normalized) || ($normalized === 'equipo informatico' && str_contains($query, 'equipos informaticos'))) {
                            $category = $name;
                        }
                    }
                }
            }
            if ($category !== null) {
                return $this->listProducts(array_values(array_filter($products, static fn(array $p): bool => in_array($category, $p['categories'], true))), $state, 'Categoría: ' . $category);
            }
            $selectedId = null;
            $buy = false;
            if ($callback !== null && preg_match('/^(product|buy):(\d+)$/', $callback, $match)) {
                $selectedId = (int) $match[2];
                $buy = $match[1] === 'buy';
                if ($buy && ($state['selected'] ?? null) !== $selectedId) {
                    return $this->invalid($state, 'Primero selecciona el producto que deseas consultar.');
                }
            } elseif ($state['step'] === 'select' && ctype_digit($query)) {
                $selectedId = $state['choices'][(int) $query - 1] ?? -1;
            } elseif ($state['step'] === 'product' && in_array($query, ['si', 'quiero comprar este producto', 'comprar'], true)) {
                $selectedId = $state['selected'];
                $buy = true;
            }
            if ($selectedId !== null) {
                foreach ($products as $product) {
                    if ($product['id'] === $selectedId) {
                        return $buy ? $this->purchase($product, $state) : $this->showProduct($product, $state);
                    }
                }
                return $this->invalid($state, 'Ese producto no pertenece al catálogo actual. Usa Ver catálogo para elegir otro.');
            }
            if ($callback !== null) {
                return $this->invalid($state, 'Ese botón ya no corresponde a una opción válida.');
            }
            if (preg_match('/\b(?:pedido|pago|carrito|reembolso|cancelar compra)\b/', $query)) {
                return $this->support($state, "Solo consulto el catálogo; no gestiono pagos, carritos ni pedidos.\n\n");
            }
            $openQuestion = preg_match('/\b(?:comparar|compara|diferencia|diferencias|recomienda|recomiendas|recomendacion|mejor|explica|explicame|sirve|serviria|para que|cual elegir)\b/', $query) === 1;
            $matches = $this->matchingProducts($text, $products);
            if (!$openQuestion && $matches !== []) {
                return count($matches) === 1 ? $this->showProduct($matches[0], $state) : $this->listProducts($matches, $state, 'Encontré estos productos. Elige uno:');
            }
            if (!$openQuestion && ($command === '/buscar' || $state['step'] === 'search' || preg_match('/\b(?:precio|cuesta|cuestan|comprar|producto)\b/', $query))) {
                $state['step'] = 'search';
                return $this->invalid($state, 'No encontré ese producto. Puedes elegir: ' . implode(', ', array_column($products, 'name')) . '.');
            }
            if (!$openQuestion && $state['step'] === 'category') {
                return $this->invalid($state, 'Esa categoría no existe. Elige una de las categorías mostradas o vuelve al menú.');
            }
            $answer = ($this->openAi)($text, $products);
            if (trim($answer) === 'FUERA_DE_ALCANCE') {
                return $this->invalid($state, 'Solo atiendo consultas sobre el catálogo de Tienda Electrónica.');
            }
            $state = ['step' => 'menu', 'attempts' => 0];
            return $this->reply($answer . "\n\n¿Necesitas algo más? Puedes consultar el catálogo o volver al menú.");
        } catch (ServiceFailure $failure) {
            $state['last_error'] = ['servicio' => $failure->service, 'http' => $failure->status];
            return $this->reply($failure->service === 'OpenAI'
                ? 'La conversación con IA no está disponible en este momento. Puedes seguir consultando productos con los botones del catálogo.'
                : 'No puedo obtener el catálogo en este momento. Intenta nuevamente o visita el sitio de atención de la tienda.');
        }
    }
}
