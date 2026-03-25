<?php
session_start();

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tienda');

// Crear conexión
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Configurar charset
$conn->set_charset("utf8");

// Función para verificar si el usuario está logueado
function verificarLogin() {
    if (!isset($_SESSION['empleado_id']) && !isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Función para verificar si es administrador
function verificarAdmin() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Función para verificar si es empleado
function verificarEmpleado() {
    if (!isset($_SESSION['empleado_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Obtener información del empleado logueado
function obtenerEmpleadoActual() {
    global $conn;
    
    if (isset($_SESSION['empleado_id'])) {
        $sql = "SELECT * FROM empleados WHERE empleado_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['empleado_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    return null;
}

// Obtener información del administrador logueado
function obtenerAdminActual() {
    global $conn;
    
    if (isset($_SESSION['admin_id'])) {
        $sql = "SELECT * FROM administradores WHERE admin_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    return null;
}

// Función para cerrar sesión
function logout() {
    session_start();
    session_destroy();
    header("Location: login.php");
    exit();
}
?>