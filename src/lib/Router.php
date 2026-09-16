<?php

declare(strict_types=1);

final class Router
{
    private const MENU_LABELS = [
        'rastrear pedido',
        'consultar catálogo',
        'consultar catalogo',
        'clima',
        'hablar con un asesor',
        'ayuda',
    ];

    private const HELP_TEXT = <<<'TXT'
Este es todo lo que puedo hacer:

• Rastrear pedido: toca «Rastrear pedido» y escribe el número de 4 dígitos de tu comprobante (ejemplo: 4821), o escríbelo directo: «pedido 4821».
• Catálogo: toca «Consultar catálogo» para ver las categorías, o pregunta por un producto: «precio de teclado».
• Clima: toca «Clima», mándame tu ubicación o escribe «/clima San Salvador».
• Asesor: toca «Hablar con un asesor» para derivar tu caso a una persona.

En cualquier momento:
/cancel — cancela la operación en curso y vuelve al menú.
/help — vuelve a mostrar esta ayuda.
TXT;

    private const LLM_SYSTEM_PROMPT = 'Eres el asistente virtual de una tienda en línea. Responde SIEMPRE en español, con máximo 3 oraciones, tono cordial y directo. Puedes conversar sobre dudas generales de compras, envíos y productos. Para acciones críticas (pagos, cambios o cancelaciones de pedidos) indica amablemente que escriba «Hablar con un asesor», y para rastrear un pedido que escriba «pedido» seguido de su número de 4 dígitos. Nunca inventes números de seguimiento, precios específicos ni confirmaciones de pago.';

    public function __construct(
        private TelegramClient $telegram,
        private PedidosClient $pedidos,
        private CatalogClient $catalogo,
        private OpenMeteoClient $climaApi,
        private LLMClient $llm
    ) {
    }

    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return;
        }
        $chatId = $message['chat']['id'] ?? null;
        if ($chatId === null) {
            return;
        }
        $text = trim((string) ($message['text'] ?? ''));
        $location = is_array($message['location'] ?? null) ? $message['location'] : null;

        if ($text === '' && $location === null) {
            Log::incoming($chatId, '(mensaje vacío o sin texto)');
            $this->telegram->sendMenu($chatId, 'No recibí texto. Usa el teclado o escribe /help para ver las opciones.');
            return;
        }

        Log::incoming($chatId, $text !== '' ? $text : sprintf('(ubicación %.4f, %.4f)', (float) $location['latitude'], (float) $location['longitude']));

        $session = Session::load($chatId);
        if ($text === '' && $location !== null) {
            if ($session['state'] === 'ASK_CITY') {
                $session = $this->readWeatherCoords($chatId, $session, (float) $location['latitude'], (float) $location['longitude']);
                Session::save($chatId, $session);
                return;
            }
            $this->telegram->sendMenu($chatId, 'Recibí tu ubicación. ¿Es para consultar el clima? Dímelo o escribe /clima, y reenvíame tu ubicación.');
            return;
        }

        $session = $this->route($chatId, $text, $session);
        Session::save($chatId, $session);
    }

    public function handleCallback(array $callbackQuery): void
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $data = (string) ($callbackQuery['data'] ?? '');
        if ($chatId === null) {
            return;
        }
        $this->telegram->answerCallbackQuery((string) ($callbackQuery['id'] ?? ''), 'Consultando…');
        Log::incoming($chatId, '[inline] ' . $data);

        $session = Session::load($chatId);
        if (str_starts_with($data, 'cat:')) {
            $session = $this->readCatalogCategory($chatId, substr($data, 4), $session);
        } elseif ($data === 'menu') {
            $session = $this->showMenu($chatId, 'Menú principal.', $session);
        }
        Session::save($chatId, $session);
    }

    private function route(int|string $chatId, string $text, array $s): array
    {
        if ($this->isStart($text)) {
            Session::reset($chatId);
            return $this->showMenu($chatId, '¡Hola! Soy el asistente virtual de la tienda. Puedo ayudarte a rastrear tu pedido, consultar el catálogo o revisar el clima. ¿Qué deseas hacer hoy?', Session::load($chatId));
        }
        if ($this->isCancel($text)) {
            Session::reset($chatId);
            return $this->showMenu($chatId, 'Operación cancelada. Volvimos al menú principal.', Session::load($chatId));
        }
        if ($this->isHelp($text)) {
            $this->telegram->sendMessage($chatId, self::HELP_TEXT, TelegramClient::menuKeyboard());
            return $s;
        }

        return match ($s['state']) {
            'ASK_ORDER' => $this->readOrder($chatId, $text, $s),
            'ASK_PRODUCT' => $this->readProduct($chatId, $text, $s),
            'ASK_CITY' => $this->readCity($chatId, $text, $s),
            'CONFIRM_SUPPORT' => $this->readSupportConfirm($chatId, $text, $s),
            'CONFIRM_CHANGE' => $this->readChangeConfirm($chatId, $text, $s),
            'OFFER_HUMAN' => $this->readOfferHuman($chatId, $text, $s),
            'API_RETRY' => $this->readApiRetry($chatId, $text, $s),
            default => $this->fromMenu($chatId, $text, $s),
        };
    }

    private function fromMenu(int|string $chatId, string $text, array $s): array
    {
        $lower = mb_strtolower($text);

        if (preg_match('/\b(pedido|paquete|orden|rastrear|env[ií]o|gu[ií]a)\b|d[oó]nde (est[aá]|va|viene) mi/u', $lower)) {
            $number = $this->extractOrderNumber($text);
            if ($number !== null) {
                return $this->runOrder($chatId, $s, $number);
            }
            $s['state'] = 'ASK_ORDER';
            $s['attempts'] = 0;
            $this->telegram->sendMenu($chatId, 'Con gusto. Escribe tu número de pedido: son 4 dígitos numéricos que encuentras en tu correo de compra.');
            return $s;
        }

        if ($this->matchesCatalogIntent($lower)) {
            $product = $this->extractProduct($text);
            if ($product === null) {
                return $this->runCatalogCategories($chatId, $s);
            }
            return $this->runCatalogSearch($chatId, $s, $product);
        }

        $city = $this->extractCity($text);
        if ($city !== null) {
            return $this->runWeather($chatId, $s, $city);
        }
        if (preg_match('/\bclima\b|\/clima\b/u', $lower)) {
            $s['state'] = 'ASK_CITY';
            $s['attempts'] = 0;
            $this->telegram->sendMenu($chatId, 'Claro, dime de qué ciudad quieres el clima (ejemplo: San Salvador). También puedes enviarme tu ubicación.');
            return $s;
        }

        if (preg_match('/\b(asesor|asesora|humano|soporte|persona real|atenci[oó]n al cliente|queja|reclamo|agente)\b/u', $lower)) {
            return $this->askSupportConfirm($chatId, $s);
        }

        if (preg_match('/\b(hola|buenas|hola que tal|buenos dias|buenas tardes|buenas noches|iniciar|empezar|hi)\b/u', $lower)) {
            return $this->showMenu($chatId, '¡Hola! ¿En qué te puedo ayudar? Puedo rastrear un pedido, consultar el catálogo o darte el clima de una ciudad.', $s);
        }

        if (preg_match('/\b(gracias|adi[oó]s|hasta luego|chao|listo|nada|eso es todo)\b/u', $lower)) {
            $this->telegram->sendMenu($chatId, '¡Ha sido un gusto ayudarte! Si necesitas algo más, escribe /start. ¡Feliz día!');
            return $s;
        }

        return $this->llmFallback($chatId, $text, $s);
    }

    private function readOrder(int|string $chatId, string $text, array $s): array
    {
        if ($this->isMenuLabel($text)) {
            $this->telegram->sendMenu($chatId, 'Escribe tu número de pedido (4 dígitos) o pulsa «Cancelar» en el teclado.');
            return $s;
        }
        if (preg_match('/\b(\d{4})\b/u', $text, $m)) {
            return $this->runOrder($chatId, $s, $m[1]);
        }
        if ($this->isOtherIntent($text, $lower = mb_strtolower($text), 'order')) {
            return $this->askTopicChange($chatId, $text, $s);
        }
        return $this->invalidInput($chatId, $s, 'Formato inválido: el número de pedido debe tener exactamente 4 dígitos (ejemplo: 4821). Escribe el número:', 'ASK_ORDER', 'pedido');
    }

    private function readProduct(int|string $chatId, string $text, array $s): array
    {
        $lower = mb_strtolower($text);
        if (in_array($lower, ['consultar catálogo', 'consultar catalogo', 'catálogo', 'catalogo'], true)) {
            return $this->runCatalogCategories($chatId, $s);
        }
        if ($this->isMenuLabel($text)) {
            $this->telegram->sendMenu($chatId, $this->reminderFor($s));
            return $s;
        }
        if ($this->isOtherIntent($text, $lower, 'catalog')) {
            return $this->askTopicChange($chatId, $text, $s);
        }
        $product = $this->extractProduct($text) ?? $this->cleanText($text);
        if ($product === '' || mb_strlen($product) < 2) {
            return $this->invalidInput($chatId, $s, 'Escribe el nombre del producto que buscas (ejemplo: teclado o monitor):', 'ASK_PRODUCT', 'producto');
        }
        return $this->runCatalogSearch($chatId, $s, $product);
    }

    private function readCity(int|string $chatId, string $text, array $s): array
    {
        $lower = mb_strtolower($text);
        if ($this->isMenuLabel($text) && !preg_match('/\bclima\b/u', $lower)) {
            $this->telegram->sendMenu($chatId, $this->reminderFor($s));
            return $s;
        }
        if ($this->isOtherIntent($text, $lower, 'weather')) {
            return $this->askTopicChange($chatId, $text, $s);
        }
        $city = $this->cleanText((string) preg_replace('/^(\/clima|clima|el clima de|el clima en|clima en|clima de|el clima)\s*/iu', '', $text) ?? '');
        if ($city === '' || !preg_match('/\p{L}/u', $city)) {
            return $this->invalidInput($chatId, $s, 'Escribe un nombre de ciudad válido (ejemplo: San Salvador) o reenvíame tu ubicación:', 'ASK_CITY', 'ciudad');
        }
        return $this->runWeather($chatId, $s, $city);
    }

    private function readSupportConfirm(int|string $chatId, string $text, array $s): array
    {
        $lower = mb_strtolower($text);
        if (preg_match('/\b(s[ií]|claro|ok|confirmo|vale|por favor)\b/u', $lower)) {
            return $this->createSupportTicket($chatId, $s);
        }
        if (preg_match('/\b(no|cancela|mejor no|todav[ií]a no)\b/u', $lower)) {
            $s['state'] = 'MENU';
            $s['attempts'] = 0;
            $this->telegram->sendMenu($chatId, 'Sin problema, no creé ningún ticket. ¿En qué más te puedo ayudar?');
            return $s;
        }
        return $this->invalidInput($chatId, $s, 'Responde Sí para confirmar el ticket o No para cancelarlo:', 'CONFIRM_SUPPORT');
    }

    private function readChangeConfirm(int|string $chatId, string $text, array $s): array
    {
        $lower = mb_strtolower($text);
        if (preg_match('/\b(s[ií]|claro|ok|vale|correcto)\b/u', $lower)) {
            $pendingText = (string) ($s['pending']['text'] ?? '');
            $fresh = Session::reset($chatId);
            if ($pendingText !== '') {
                return $this->fromMenu($chatId, $pendingText, $fresh);
            }
            return $this->showMenu($chatId, 'Volviendo al menú principal.', $fresh);
        }
        if (preg_match('/\b(no|todav[ií]a|sigue|contin[uú]a|cancela)\b/u', $lower)) {
            $s['state'] = $s['return_state'] ?? 'MENU';
            $s['pending'] = null;
            $s['return_state'] = null;
            $this->telegram->sendMenu($chatId, 'Perfecto, seguimos con lo anterior. ' . $this->reminderFor($s));
            return $s;
        }
        return $this->invalidInput($chatId, $s, 'Responde Sí para cambiar de tema o No para continuar con lo anterior:', 'CONFIRM_CHANGE');
    }

    private function readOfferHuman(int|string $chatId, string $text, array $s): array
    {
        $lower = mb_strtolower($text);
        if (preg_match('/\b(s[ií]|claro|ok|vale|por favor)\b/u', $lower)) {
            return $this->askSupportConfirm($chatId, $s);
        }
        if (preg_match('/\b(no|menu|men[uú] principal|cancelar|salir)\b/u', $lower)) {
            $fresh = Session::reset($chatId);
            return $this->showMenu($chatId, 'De acuerdo, volvimos al menú principal. ¿En qué te puedo ayudar?', $fresh);
        }
        return $this->invalidInput($chatId, $s, 'Responde Sí para hablar con un asesor o No para volver al menú:', 'OFFER_HUMAN');
    }

    private function readApiRetry(int|string $chatId, string $text, array $s): array
    {
        $lower = mb_strtolower($text);
        $context = is_array($s['context'] ?? null) ? $s['context'] : [];
        if (preg_match('/\b(reintentar|otra vez|s[ií])\b/u', $lower)) {
            $s['attempts'] = 0;
            return $this->retryContext($chatId, $s, $context);
        }
        if (preg_match('/\b(no|menu|men[uú] principal|cancelar|salir)\b/u', $lower)) {
            $fresh = Session::reset($chatId);
            return $this->showMenu($chatId, 'De acuerdo, volvimos al menú principal. ¿En qué te puedo ayudar?', $fresh);
        }
        if ($this->isOtherIntent($text, $lower, (string) ($context['type'] ?? ''))) {
            return $this->askTopicChange($chatId, $text, $s);
        }
        $this->telegram->sendMessage($chatId, "Responde «Reintentar» o «Menú principal», por favor:", TelegramClient::retryKeyboard());
        return $s;
    }

    private function retryContext(int|string $chatId, array $s, array $context): array
    {
        match ((string) ($context['type'] ?? '')) {
            'order' => $s = $this->runOrder($chatId, $s, (string) ($context['arg'] ?? '')),
            'weather' => $s = $this->runWeather($chatId, $s, (string) ($context['arg'] ?? '')),
            'catalog' => $s = $this->runCatalogSearch($chatId, $s, (string) ($context['arg'] ?? '')),
            'categories' => $s = $this->runCatalogCategories($chatId, $s),
            default => $s = $this->showMenu($chatId, 'Volviendo al menú principal.', $s),
        };
        return $s;
    }

    private function runOrder(int|string $chatId, array $s, string $number): array
    {
        $result = $this->pedidos->track((int) $number);
        if (!($result['found'] ?? false)) {
            return $this->invalidInput(
                $chatId,
                $s,
                "No encontré ningún pedido registrado con el número <b>{$number}</b>. Verifica tu comprobante y escribe el número de nuevo:",
                'ASK_ORDER',
                'pedido'
            );
        }
        $s['state'] = 'MENU';
        $s['attempts'] = 0;
        $reply = "Tu pedido <b>#{$number}</b> se encuentra <b>{$result['status']}</b>.";
        if (!empty($result['eta'])) {
            $reply .= ' Entrega programada: <b>' . $result['eta'] . '</b>.';
        }
        $reply .= "\n\n¿Deseas consultar algo más?";
        $this->telegram->sendMenu($chatId, $reply);
        return $s;
    }

    private function runCatalogCategories(int|string $chatId, array $s): array
    {
        $categories = $this->catalogo->categories();
        if ($categories === null) {
            return $this->apiFail($chatId, $s, ['type' => 'categories']);
        }
        if ($categories === []) {
            $s['state'] = 'MENU';
            $this->telegram->sendMenu($chatId, 'El catálogo está vacío por el momento. ¿Deseas consultar algo más?');
            return $s;
        }
        $buttons = [];
        foreach (array_slice($categories, 0, 8) as $category) {
            $category = (string) $category;
            $buttons[] = [self::inlineBtn(ucfirst($category), 'cat:' . $category)];
        }
        $buttons[] = [self::inlineBtn('Volver al menú', 'menu')];
        $s['state'] = 'ASK_PRODUCT';
        $s['attempts'] = 0;
        $this->telegram->sendMessage($chatId, 'Estas son las categorías del catálogo. Elige una o escribe el nombre del producto que buscas:', ['inline_keyboard' => $buttons]);
        return $s;
    }

    private function readCatalogCategory(int|string $chatId, string $category, array $s): array
    {
        $items = $this->catalogo->byCategory($category);
        if ($items === null) {
            return $this->apiFail($chatId, $s, ['type' => 'categories']);
        }
        if ($items === []) {
            $this->telegram->sendMessage($chatId, 'No hay productos en esa categoría ahora mismo. Elige otra:');
            return $this->runCatalogCategories($chatId, $s);
        }
        $s['state'] = 'MENU';
        $s['attempts'] = 0;
        $lines = [];
        foreach (array_slice($items, 0, 8) as $item) {
            $lines[] = self::formatProduct((array) $item);
        }
        $reply = 'Productos en <b>' . htmlspecialchars($category, ENT_QUOTES) . "</b>:\n\n" . implode("\n", $lines) . "\n\n¿Deseas consultar algo más?";
        $this->telegram->sendMenu($chatId, $reply);
        return $s;
    }

    private function runCatalogSearch(int|string $chatId, array $s, string $product): array
    {
        $items = $this->catalogo->search($product);
        if ($items === null) {
            return $this->apiFail($chatId, $s, ['type' => 'catalog', 'arg' => $product]);
        }
        if ($items === []) {
            return $this->invalidInput(
                $chatId,
                $s,
                'No encontré coincidencias para <b>' . htmlspecialchars($product, ENT_QUOTES) . '</b>. Escribe el nombre de otro producto:',
                'ASK_PRODUCT',
                'producto'
            );
        }
        $s['state'] = 'MENU';
        $s['attempts'] = 0;
        $lines = [];
        foreach (array_slice($items, 0, 5) as $item) {
            $lines[] = self::formatProduct((array) $item);
        }
        $count = count($items) > 5 ? 'los primeros 5 de ' . count($items) : (string) count($items);
        $reply = 'Encontré <b>' . $count . '</b> producto(s) para «' . htmlspecialchars($product, ENT_QUOTES) . "»:\n\n" . implode("\n", $lines) . "\n\n¿Deseas consultar algo más?";
        $this->telegram->sendMenu($chatId, $reply);
        return $s;
    }

    private function runWeather(int|string $chatId, array $s, string $city): array
    {
        if ($city === '') {
            $s['state'] = 'ASK_CITY';
            $s['attempts'] = 0;
            $this->telegram->sendMenu($chatId, 'Claro, dime de qué ciudad quieres el clima (ejemplo: San Salvador). También puedes enviarme tu ubicación.');
            return $s;
        }
        $result = $this->climaApi->weather($city);
        return $this->finishWeather($chatId, $s, $result, 'ASK_CITY', self::cleanWeatherCity($city));
    }

    private function readWeatherCoords(int|string $chatId, array $s, float $latitude, float $longitude): array
    {
        $result = $this->climaApi->byCoords($latitude, $longitude);
        return $this->finishWeather($chatId, $s, $result, null, '');
    }

    private function finishWeather(int|string $chatId, array $s, array $result, ?string $retryState, string $label): array
    {
        if ($result['ok'] ?? false) {
            $s['state'] = 'MENU';
            $s['attempts'] = 0;
            $place = '<b>' . htmlspecialchars($result['city'], ENT_QUOTES);
            if ($result['country'] !== '') {
                $place .= ', ' . htmlspecialchars($result['country'], ENT_QUOTES);
            }
            $place .= '</b>';
            $reply = "En {$place} hay <b>" . number_format((float) $result['temperature'], 1, '.', '') . '°C</b> (sensación de ' . number_format((float) $result['feels'], 1, '.', '') . '°C), ' . OpenMeteoClient::describe((int) $result['code']) . ".\n\n¿Deseas consultar algo más?";
            $this->telegram->sendMenu($chatId, $reply);
            return $s;
        }
        if (($result['reason'] ?? 'api_down') === 'city_not_found') {
            return $this->invalidInput(
                $chatId,
                $s,
                "No encontré la ciudad «{$this->e($label)}». Verifica el nombre e intenta de nuevo:",
                $retryState ?? 'ASK_CITY',
                'ciudad'
            );
        }
        return $this->apiFail($chatId, $s, ['type' => 'weather', 'arg' => $label]);
    }

    private function askSupportConfirm(int|string $chatId, array $s): array
    {
        $s['state'] = 'CONFIRM_SUPPORT';
        $s['attempts'] = 0;
        $this->telegram->sendMessage($chatId, 'Puedo abrir un ticket de soporte para que un asesor humano te contacte. Esta acción no se puede deshacer. ¿Confirmas?', TelegramClient::yesNoKeyboard());
        return $s;
    }

    private function createSupportTicket(int|string $chatId, array $s): array
    {
        $ticket = 'T-' . random_int(1000, 9999);
        $link = (string) (Env::get('SUPPORT_URL') ?? 'https://t.me/soporte_tienda');
        $s['state'] = 'MENU';
        $s['attempts'] = 0;
        Log::event('ticket creado ' . $ticket . ' para chat ' . $chatId);
        $this->telegram->sendMenu($chatId, "Listo, creé el ticket <b>#{$ticket}</b>. Un asesor revisará tu caso y te contactará por este chat. También puedes escribirnos en {$link}.\n\n¿Algo más en lo que te pueda ayudar?");
        return $s;
    }

    private function askTopicChange(int|string $chatId, string $text, array $s): array
    {
        $s['return_state'] = $s['state'];
        $s['pending'] = ['text' => $text];
        $s['state'] = 'CONFIRM_CHANGE';
        $this->telegram->sendMessage($chatId, 'Parece que quieres consultar otra cosa. ¿Deseas cancelar la operación actual y atender tu nueva solicitud?', TelegramClient::yesNoKeyboard());
        return $s;
    }

    private function llmFallback(int|string $chatId, string $text, array $s): array
    {
        $answer = $this->llm->chat($text, self::LLM_SYSTEM_PROMPT);
        if ($answer === null) {
            Log::outgoing($chatId, '(LLM no disponible, respuesta de respaldo)');
            $this->telegram->sendMenu($chatId, 'No pude procesar tu consulta con el asistente inteligente en este momento. Escribe /help para ver todo lo que puedo hacer, o usa el teclado de abajo.');
            return $s;
        }
        $this->telegram->sendMenu($chatId, $answer);
        return $s;
    }

    private function showMenu(int|string $chatId, string $text, array $s): array
    {
        $s['state'] = 'MENU';
        $s['attempts'] = 0;
        $s['context'] = null;
        $s['pending'] = null;
        $s['return_state'] = null;
        $this->telegram->sendMenu($chatId, $text);
        return $s;
    }

    private function apiFail(int|string $chatId, array $s, array $context): array
    {
        Log::error('apifail', 'servicio no disponible, tipo=' . (string) ($context['type'] ?? '?') . ' chat=' . $chatId);
        $s['state'] = 'API_RETRY';
        $s['context'] = $context;
        $this->telegram->sendMessage($chatId, 'No pude conectarme con el sistema en este momento. ¿Quieres que intente de nuevo?', TelegramClient::retryKeyboard());
        return $s;
    }

    private function invalidInput(int|string $chatId, array $s, string $message, string $state, ?string $topic = null): array
    {
        $attempts = (int) ($s['attempts'] ?? 0) + 1;
        if ($attempts >= 3) {
            $s['state'] = 'OFFER_HUMAN';
            $s['attempts'] = 0;
            $s['context'] = null;
            $this->telegram->sendMessage($chatId, 'Se agotaron los 3 intentos. ¿Quieres que te derive con un asesor humano?', TelegramClient::yesNoKeyboard());
            return $s;
        }
        $s['attempts'] = $attempts;
        $s['state'] = $state;
        $suffix = $topic !== null
            ? "\n\nSi prefieres, escribe «cancelar» para volver al menú."
            : '';
        $this->telegram->sendMessage($chatId, "Intento {$attempts} de 3.\n\n" . $message . $suffix, TelegramClient::menuKeyboard());
        return $s;
    }

    private function reminderFor(array $s): string
    {
        return match ($s['state'] ?? 'MENU') {
            'ASK_ORDER' => 'Escribe tu número de pedido (4 dígitos).',
            'ASK_PRODUCT' => 'Escribe el nombre del producto que buscas.',
            'ASK_CITY' => '¿De qué ciudad quieres el clima?',
            'OFFER_HUMAN' => 'Responde Sí para hablar con un asesor o No para volver al menú.',
            'CONFIRM_SUPPORT' => 'Responde Sí para confirmar el ticket o No para cancelarlo.',
            'CONFIRM_CHANGE' => 'Responde Sí para cambiar de tema o No para continuar con lo anterior.',
            'API_RETRY' => 'Responde Reintentar para volver a intentar o Menú principal.',
            default => '¿En qué te puedo ayudar?',
        };
    }

    private function isStart(string $text): bool
    {
        return (bool) preg_match('/^\/start\b/u', mb_strtolower($text));
    }

    private function isCancel(string $text): bool
    {
        return (bool) (preg_match('/^\/cancel\b/u', mb_strtolower($text)) ?: preg_match('/\b(cancelar|cancela|salir|volver)\b/u', mb_strtolower($text)));
    }

    private function isHelp(string $text): bool
    {
        $lower = mb_strtolower($text);
        return (bool) (
            preg_match('/^\/(help|ayuda)\b/u', $lower)
            ?: preg_match('/\b(ayuda|ay[uú]dame|socorro)\b|qu[eé] puedes hacer/u', $lower)
        );
    }

    private function isMenuLabel(string $text): bool
    {
        return in_array(mb_strtolower($text), self::MENU_LABELS, true);
    }

    private function isOtherIntent(string $text, string $lower, string $current): bool
    {
        if ($current !== 'order' && $this->matchesOrderIntent($lower)) {
            return true;
        }
        if ($current !== 'catalog' && $this->matchesCatalogIntent($lower)) {
            return true;
        }
        if ($current !== 'weather' && $this->extractCity($text) !== null) {
            return true;
        }
        if ($current !== 'support' && $this->matchesSupportIntent($lower)) {
            return true;
        }
        return false;
    }

    private function matchesOrderIntent(string $lower): bool
    {
        return (bool) preg_match('/\b(pedido|paquete|orden|rastrear|env[ií]o|gu[ií]a)\b|d[oó]nde (est[aá]|va|viene) mi/u', $lower);
    }

    private function matchesCatalogIntent(string $lower): bool
    {
        return (bool) preg_match('/\b(cat[aá]logo|precio|precios|productos?|busca|buscar|busco|tienen|stock|disponible|disponibilidad|cuesta|venden)\b/u', $lower);
    }

    private function matchesSupportIntent(string $lower): bool
    {
        return (bool) preg_match('/\b(asesor|asesora|humano|soporte|persona real|atenci[oó]n al cliente|queja|reclamo|agente)\b/u', $lower);
    }

    private function extractOrderNumber(string $text): ?string
    {
        return preg_match('/\b(\d{4})\b/u', $text, $m) ? $m[1] : null;
    }

    private function extractProduct(string $text): ?string
    {
        $t = $this->cleanText($text);
        foreach ([
            '/^(precio|costo) (de|del) /iu',
            '/^(cuanto|qu[eé]) (cuesta|vale|vale el|vale la) (el|la|los|las)? ?/iu',
            '/^(tienen|hay|venden|vende|manejan) /iu',
            '/^(busco|buscar|busca|dame|muestra|mostrar|quiero|ver)\s+/iu',
            '/^(el|la|los|las|un|una|unos|unas|de|sobre) /iu',
        ] as $pattern) {
            $t = (string) preg_replace($pattern, '', $t);
        }
        $t = trim($t);
        if ($t === '' || in_array(mb_strtolower($t), ['catalogo', 'producto', 'productos', 'precio', 'sí', 'si', 'no'], true)) {
            return null;
        }
        return mb_strlen($t) > 60 ? mb_substr($t, 0, 60) : $t;
    }

    private function extractCity(string $text): ?string
    {
        $lower = mb_strtolower($text);
        if (preg_match('/^\/clima\s*(.*)$/u', $text, $m)) {
            return $this->cleanWeatherCity($m[1]);
        }
        if (preg_match('/\bclima\b/u', $lower)) {
            $rest = (string) preg_replace('/.*\bclima\b\s*(en|de|del|para|el|la)?\s*/iu', '', $text, 1);
            return $this->cleanWeatherCity($rest);
        }
        return null;
    }

    private function cleanWeatherCity(string $city): string
    {
        return $this->cleanText($city);
    }

    private function cleanText(string $text): string
    {
        $text = trim(preg_replace('/[¿?!¡.,;:"«»]/u', ' ', $text) ?? '');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private static function formatProduct(array $item): string
    {
        $title = htmlspecialchars(mb_substr(trim((string) ($item['title'] ?? 'producto')), 0, 60), ENT_QUOTES);
        return '• <b>' . $title . '</b> — $' . number_format((float) ($item['price'] ?? 0), 2);
    }

    private static function inlineBtn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES);
    }
}
