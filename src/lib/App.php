<?php

declare(strict_types=1);

final class App
{
    private static ?TelegramClient $telegram = null;
    private static ?Router $router = null;

    public static function token(): string
    {
        $token = Env::get('BOT_TOKEN');
        if ($token === null) {
            fwrite(STDERR, "Error: BOT_TOKEN no definido. Copia .env.example a .env y coloca tu token.\n");
            exit(1);
        }
        return $token;
    }

    public static function telegram(): TelegramClient
    {
        if (self::$telegram === null) {
            self::$telegram = new TelegramClient(self::token());
        }
        return self::$telegram;
    }

    public static function router(): Router
    {
        if (self::$router === null) {
            self::$router = new Router(
                self::telegram(),
                new PedidosClient(),
                new CatalogClient(),
                new OpenMeteoClient(),
                LLMClient::fromEnv()
            );
        }
        return self::$router;
    }
}
