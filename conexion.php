<?php
// conexion.php
$host = "localhost";
$usuario = "root";
$password = "";
$basedatos = "tienda";

$conexion = new mysqli($host, $usuario, $password, $basedatos);

// Verificar conexión
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>