<?php
header("Content-Type: application/json; charset=utf-8");

$action = $_GET["action"] ?? "";
$categoria = $_GET["categoria"] ?? "";
$id = $_GET["id"] ?? "";

if ($action === "catalogo") {
    $laptops = [
        ["id" => 1, "marca" => "Dell", "modelo" => "Inspiron 15", "precio" => 750, "stock" => 4],
        ["id" => 2, "marca" => "HP", "modelo" => "Pavilion 14", "precio" => 620, "stock" => 2],
        ["id" => 3, "marca" => "Lenovo", "modelo" => "ThinkPad E14", "precio" => 890, "stock" => 0]
    ];

    $smartphones = [
        ["id" => 10, "marca" => "Samsung", "modelo" => "Galaxy S23", "precio" => 850, "colores" => ["Negro", "Verde"], "stock" => 3],
        ["id" => 11, "marca" => "Apple", "modelo" => "iPhone 14", "precio" => 990, "colores" => ["Blanco", "Azul"], "stock" => 1]
    ];

    $accesorios = [
        ["id" => 20, "producto" => "Audífonos Bluetooth Sony", "precio" => 85, "stock" => 10],
        ["id" => 21, "producto" => "Mouse Inalámbrico Logitech", "precio" => 25, "stock" => 15]
    ];

    if ($categoria === "laptops") {
        echo json_encode(["status" => "success", "data" => $laptops]);
    } elseif ($categoria === "smartphones") {
        echo json_encode(["status" => "success", "data" => $smartphones]);
    } elseif ($categoria === "accesorios") {
        echo json_encode(["status" => "success", "data" => $accesorios]);
    } else {
        echo json_encode(["status" => "error", "message" => "Categoría no encontrada"]);
    }
    exit;
}

if ($action === "pedido") {
    if ($id === "5678") {
        echo json_encode([
            "status" => "success",
            "data" => [
                "pedido_id" => "5678",
                "estado" => "En camino",
                "fecha_entrega" => "Mañana antes de las 5:00 PM"
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Pedido no encontrado"]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Acción no válida"]);
?>
