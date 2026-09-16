<?php

declare(strict_types=1);

final class PedidosClient
{
    private const STATUSES = ['Recibido', 'En preparación', 'En tránsito', 'Entregado'];

    public function track(int $orderId): array
    {
        if ($orderId < 1000 || $orderId > 9999 || $orderId % 10 === 0) {
            return ['found' => false];
        }
        $status = self::STATUSES[$orderId % 4];
        $eta = null;
        if ($status === 'Entregado') {
            $eta = 'entregado el ' . date('d/m/Y', (int) strtotime('-' . ($orderId % 5 + 1) . ' day'));
        } elseif ($status !== 'Recibido') {
            $eta = date('d/m/Y', (int) strtotime('+' . ($orderId % 3 + 1) . ' day'));
        }
        return [
            'found' => true,
            'status' => $status,
            'eta' => $eta,
        ];
    }
}
