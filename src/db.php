<?php
// src/db.php

class Database {
    private static $pdo = null;

public static function getConnection() {
    if (self::$pdo === null) {
        $envPath = getenv('DB_PATH') ?: '../storage/odontobot.sqlite';
        
        if (strpos($envPath, '/') !== 0 && !preg_match('/^[a-zA-Z]:\\\\/', $envPath)) {
            $dbPath = __DIR__ . '/../' . ltrim($envPath, './');
        } else {
            $dbPath = $envPath;
        }

        $dir = dirname($dbPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        self::$pdo = new PDO("sqlite:" . $dbPath);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::initTables();
    }
    return self::$pdo;
}

    private static function initTables() {
        $db = self::$pdo;
        
        // Tabla de citas agendadas
        $db->exec("CREATE TABLE IF NOT EXISTS appointments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            phone TEXT NOT NULL,
            name TEXT NOT NULL,
            service TEXT NOT NULL,
            date TEXT NOT NULL,
            time TEXT NOT NULL,
            status TEXT DEFAULT 'ACTIVE', -- ACTIVE, CANCELLED
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Tabla de estados de conversación por chat_id
        $db->exec("CREATE TABLE IF NOT EXISTS user_states (
            chat_id INTEGER PRIMARY KEY,
            state TEXT NOT NULL,
            data_json TEXT DEFAULT '{}',
            attempts INTEGER DEFAULT 0
        )");
    }

    // --- MANEJO DE ESTADOS ---
    public static function getState($chatId) {
        $db = self::getConnection();
        $stmt = $db->prepare("SELECT * FROM user_states WHERE chat_id = ?");
        $stmt->execute([$chatId]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'state' => $row['state'],
                'data' => json_decode($row['data_json'], true) ?? [],
                'attempts' => (int)$row['attempts']
            ];
        }
        return ['state' => 'MAIN_MENU', 'data' => [], 'attempts' => 0];
    }

    public static function setState($chatId, $state, $data = [], $attempts = 0) {
        $db = self::getConnection();
        $stmt = $db->prepare("INSERT INTO user_states (chat_id, state, data_json, attempts) 
            VALUES (?, ?, ?, ?) 
            ON CONFLICT(chat_id) DO UPDATE SET 
            state = excluded.state, 
            data_json = excluded.data_json, 
            attempts = excluded.attempts");
        $stmt->execute([$chatId, $state, json_encode($data), $attempts]);
    }

    public static function resetState($chatId) {
        self::setState($chatId, 'MAIN_MENU', [], 0);
    }

    // --- DISPONIBILIDAD Y CITAS ---
    
    // Verifica si un slot específico (fecha + hora) ya está reservado
    public static function isSlotTaken($date, $time) {
        $db = self::getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE date = ? AND time = ? AND status = 'ACTIVE'");
        $stmt->execute([$date, $time]);
        $res = $stmt->fetch();
        return $res['count'] > 0;
    }

    // Retorna lista de horas libres para un día específico
    public static function getAvailableTimes($date) {
        $allTimes = ['9:00 am', '11:00 am', '1:00 pm', '2:00 pm', '3:00 pm'];
        $db = self::getConnection();
        $stmt = $db->prepare("SELECT time FROM appointments WHERE date = ? AND status = 'ACTIVE'");
        $stmt->execute([$date]);
        $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_diff($allTimes, $taken));
    }

    // Guardar nueva cita
    public static function createAppointment($phone, $name, $service, $date, $time) {
        if (self::isSlotTaken($date, $time)) {
            return false; // Previene doble reservación
        }
        $db = self::getConnection();
        $stmt = $db->prepare("INSERT INTO appointments (phone, name, service, date, time) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$phone, $name, $service, $date, $time]);
    }

    // Buscar citas activas por número de teléfono
    public static function getAppointmentsByPhone($phone) {
        $db = self::getConnection();
        $stmt = $db->prepare("SELECT * FROM appointments WHERE phone = ? AND status = 'ACTIVE' ORDER BY id DESC");
        $stmt->execute([$phone]);
        return $stmt->fetchAll();
    }

    // Cancelar cita
    public static function cancelAppointment($id) {
        $db = self::getConnection();
        $stmt = $db->prepare("UPDATE appointments SET status = 'CANCELLED' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Reprogramar cita
    public static function rescheduleAppointment($id, $newDate, $newTime) {
        if (self::isSlotTaken($newDate, $newTime)) {
            return false;
        }
        $db = self::getConnection();
        $stmt = $db->prepare("UPDATE appointments SET date = ?, time = ? WHERE id = ?");
        return $stmt->execute([$newDate, $newTime, $id]);
    }
}
