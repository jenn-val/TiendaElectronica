<?php
session_start();
include 'conexion.php';

// Variables para mensajes
$error_login = '';
$error_register = '';
$success_message = '';

// Función para validar formato de nombre (primera mayúscula, resto minúsculas)
function validarFormatoNombre($nombre) {
    // Permite nombres simples o compuestos (Juan, María José, Ana María)
    // La primera letra de cada palabra debe ser mayúscula, resto minúsculas
    return preg_match('/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*(?:\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*)*$/', $nombre);
}

// Función para validar formato de apellido (primera mayúscula, resto minúsculas)
function validarFormatoApellido($apellido) {
    // Apellidos simples (Pérez, González, Rodríguez)
    return preg_match('/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+$/', $apellido);
}

// Función para capitalizar nombres correctamente
function capitalizarNombre($nombre) {
    return mb_convert_case($nombre, MB_CASE_TITLE, "UTF-8");
}

// PROCESAR LOGIN DE ADMINISTRADORES
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] == 'login') {
    
    $email = trim($_POST["email"] ?? '');
    $password_ingresada = $_POST["password"] ?? ''; 

    if (empty($email) || empty($password_ingresada)) {
        $error_login = "Por favor, ingrese su correo y contraseña.";
    } else {
        if ($conexion) {
            // Buscar en tabla de administradores
            $sql = $conexion->prepare("SELECT admin_id, nombre, password FROM administradores WHERE email = ?");
            
            if ($sql === false) {
                 $error_login = "Error interno del servidor (fallo en la consulta).";
                 error_log("Error de preparación SQL: " . $conexion->error);
            } else {
                $sql->bind_param("s", $email);
                $sql->execute();
                $resultado = $sql->get_result();

                if ($resultado->num_rows === 1) {
                    $admin = $resultado->fetch_assoc();

                    // Verificar contraseña
                    if (password_verify($password_ingresada, $admin['password'])) {
                        // ✅ ÉXITO: Login correcto
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_nombre'] = $admin['nombre'];
                        $_SESSION['user_type'] = 'admin'; // Para identificar tipo de usuario
                        
                        header('Location: admin_dashboard.php');
                        exit;
                    
                    } else {
                        // ❌ Contraseña incorrecta
                        $error_login = "Contraseña incorrecta. Inténtelo de nuevo.";
                    }

                } else {
                    // ❌ Correo no existe
                    $error_login = "El correo electrónico no está registrado.";
                }
                $sql->close();
            }
        } else {
            $error_login = "Error de conexión a la base de datos.";
        }
    }
}

// PROCESAR REGISTRO DE ADMINISTRADORES
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] == 'register_admin') {
    
    // 1. Recibir datos - TODOS LOS CAMPOS SON OBLIGATORIOS
    $nombre = trim($_POST['nombre'] ?? '');
    $primer_apellido = trim($_POST['primer_apellido'] ?? '');
    $segundo_apellido = trim($_POST['segundo_apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // 2. TODOS los campos son obligatorios
    if (empty($nombre) || empty($primer_apellido) || empty($segundo_apellido) || 
        empty($email) || empty($telefono) || empty($password) || empty($password2)) {
        $error_register = "Todos los campos son obligatorios.";
    }
    // 3. Validar formato de nombres (primera letra mayúscula, resto minúsculas)
    elseif (!validarFormatoNombre($nombre)) {
        $error_register = "El nombre debe comenzar con mayúscula y el resto en minúsculas. Ejemplo: 'Juan' o 'María José'.";
    }
    elseif (!validarFormatoApellido($primer_apellido)) {
        $error_register = "El primer apellido debe comenzar con mayúscula y el resto en minúsculas. Ejemplo: 'Pérez'.";
    }
    elseif (!validarFormatoApellido($segundo_apellido)) {
        $error_register = "El segundo apellido debe comenzar con mayúscula y el resto en minúsculas. Ejemplo: 'González'.";
    }
    // 4. Validar email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_register = "El formato del correo electrónico no es válido.";
    }
    // 5. Contraseñas iguales
    elseif ($password !== $password2) {
        $error_register = "Las contraseñas no coinciden.";
    }
    // 6. Validar contraseña (10 caracteres, mayúscula y símbolo)
    elseif (strlen($password) !== 10) {
        $error_register = "La contraseña debe tener exactamente 10 caracteres.";
    }
    elseif (!preg_match('/[A-Z]/', $password)) {
        $error_register = "La contraseña debe contener al menos una letra mayúscula.";
    }
    elseif (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error_register = "La contraseña debe contener al menos un símbolo (ej: @ # $ % & * + - .).";
    }
    // 7. Validar teléfono (10 dígitos) - AHORA ES OBLIGATORIO
    elseif (!preg_match('/^\d{10}$/', $telefono)) {
        $error_register = "El teléfono debe tener exactamente 10 dígitos.";
    }
    else {
        // 8. Verificar si email ya existe
        if ($conexion) {
            $check = $conexion->prepare("SELECT admin_id FROM administradores WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            $resultado = $check->get_result();

            if ($resultado->num_rows > 0) {
                $error_register = "Este correo ya está registrado.";
            } else {
                // 9. Capitalizar nombres para asegurar formato correcto antes de insertar
                $nombre = capitalizarNombre($nombre);
                $primer_apellido = capitalizarNombre($primer_apellido);
                $segundo_apellido = capitalizarNombre($segundo_apellido);
                
                // 10. Hash de contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // 11. Insertar administrador
                $sql = $conexion->prepare("
                    INSERT INTO administradores 
                    (nombre, primer_apellido, segundo_apellido, email, password, telefono)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                if ($sql === false) {
                    $error_register = "Error al preparar la consulta.";
                } else {
                    $sql->bind_param(
                        "ssssss",
                        $nombre,
                        $primer_apellido,
                        $segundo_apellido,
                        $email,
                        $password_hash,
                        $telefono
                    );

                    if ($sql->execute()) {
                        $success_message = "¡Administrador registrado exitosamente!";
                        // Limpiar campos del formulario
                        $_POST = array();
                    } else {
                        error_log("Error al registrar administrador: " . $sql->error);
                        $error_register = "Error al crear la cuenta. Intenta de nuevo.";
                    }
                    $sql->close();
                }
            }
            $check->close();
        } else {
            $error_register = "Error de conexión a la base de datos.";
        }
    }
}

// Mostrar mensaje de éxito si existe en sesión
if (isset($_SESSION['registro_exitoso'])) {
    $success_message = $_SESSION['registro_exitoso'];
    unset($_SESSION['registro_exitoso']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Administrador - SAFI Electrónicos</title>
  <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
  <style>
    /* MANTENGO TODOS TUS ESTILOS ORIGINALES CON COLOR NARANJA */
    @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap');

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Montserrat', sans-serif;
    }

    body {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      min-height: 100vh;
      background: linear-gradient(135deg, #ffd084, #fdd390); /* COLOR NARANJA ORIGINAL */
      padding: 20px;
    }

    .container {
      max-width: 990px;
      width: 100%;
      height: 600px;
      background-color: #fff;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      overflow: hidden;
      position: relative;
      display: flex;
    }

    .container-form {
      width: 50%;
      height: 100%;
      overflow: hidden;
      display: flex;
    }

    form {
      min-width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      transition: transform 0.6s ease-in-out;
      padding: 0 50px;
    }

    h2 {
      font-size: 28px;
      margin-bottom: 20px;
      color: #333;
    }

    .social-networks {
      display: flex;
      gap: 15px;
      margin-bottom: 25px;
    }

    .social-networks ion-icon {
      font-size: 22px;
      color: #FF6B1A; /* COLOR NARANJA ORIGINAL */
      border: 1px solid #C9CCCB;
      border-radius: 50%;
      padding: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .social-networks ion-icon:hover {
      background: #FF6B1A; /* COLOR NARANJA ORIGINAL */
      color: #fff;
    }

    span {
      font-size: 13px;
      color: #666;
      margin-bottom: 20px;
      text-align: center;
    }

    /* AQUÍ AJUSTO EL ANCHO DE LOS INPUTS PARA QUE SEAN IGUALES */
    .container-input {
      width: 310px; /* MISMO ANCHO PARA TODOS */
      height: 45px;
      display: flex;
      align-items: center;
      gap: 10px;
      background: #f3f4f6;
      border-radius: 8px;
      padding: 0 15px;
      margin-bottom: 15px;
      border: 1px solid transparent;
      transition: border-color 0.3s ease;
    }

    .container-input:focus-within {
      border-color: #FF6B1A;
    }

    .container-input.multi {
      height: auto;
      min-height: 45px;
      background: transparent;
      padding: 0;
      width: 310px; /* MISMO ANCHO */
      border: none;
    }

    .dual-inputs {
      display: flex;
      width: 100%;
      gap: 10px;
    }

    .dual-inputs .input-group {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 10px;
      background: #f3f4f6;
      border-radius: 8px;
      padding: 0 15px;
      height: 45px;
      border: 1px solid transparent;
      transition: border-color 0.3s ease;
    }

    .dual-inputs .input-group:focus-within {
      border-color: #FF6B1A;
    }

    /* CLASE ESPECÍFICA PARA NOMBRES Y APELLIDOS */
    .capitalize-text {
      text-transform: capitalize;
    }

    .dual-inputs input {
      width: 100%;
      border: none;
      background: transparent;
      outline: none;
      font-size: 14px;
      color: #333;
      /* REMOVIDO: text-transform: capitalize; */
    }

    .container-input ion-icon {
      color: #FF6B1A; /* COLOR NARANJA ORIGINAL */
    }

    .container-input input {
      width: 100%;
      border: none;
      background: transparent;
      outline: none;
      font-size: 11px;
      color: #333;
      /* REMOVIDO: text-transform: capitalize; */
    }

    .password-toggle {
      cursor: pointer;
      color: #777;
      transition: color 0.3s ease;
    }

    .password-toggle:hover {
      color: #FF6B1A; /* COLOR NARANJA ORIGINAL */
    }

    a {
      color: #FF6B1A; /* COLOR NARANJA ORIGINAL */
      font-size: 13px;
      margin-top: 10px;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }

    .button {
      width: 180px;
      height: 45px;
      background: #FF6B1A; /* COLOR NARANJA ORIGINAL */
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      margin-top: 20px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .button:hover {
      background: #e55a0f;
    }

    .msg-pass {
      color: red;
      font-size: 12px;
      margin-top: -5px;
      margin-bottom: 10px;
      height: 15px;
      width: 310px; /* MISMO ANCHO */
      text-align: left;
    }

    .length-msg {
      color: #666;
      font-size: 11px;
      margin-top: -5px;
      margin-bottom: 10px;
      height: 15px;
      width: 310px; /* MISMO ANCHO */
      text-align: left;
    }

    /* Animación - Escritorio */
    .sign-up {
      transform: translateX(-100%);
    }

    .container.toggle .sign-in {
      transform: translateX(100%);
    }

    .container.toggle .sign-up {
      transform: translateX(0);
    }

    .container-welcome {
      position: absolute;
      width: 50%;
      height: 100%;
      display: flex;
      align-items: center;
      transform: translateX(100%);
      background-color: #FF6B1A; /* COLOR NARANJA ORIGINAL */
      transition: transform 0.5s ease-in-out, border-radius 0.5s ease-in-out;
      overflow: hidden;
      border-radius: 50% 0 0 50%;
    }

    .container.toggle .container-welcome {
      transform: translateX(0);
      border-radius: 0 50% 50% 0%;
      background-color: #FF6B1A; /* COLOR NARANJA ORIGINAL */
    }

    .container-welcome .welcome {
      position: absolute;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
      padding: 0 50px;
      color: white;
      transition: transform 0.5s ease-in-out;
    }

    .welcome-sign-in {
      transform: translateX(100%);
    }

    .container-welcome h3 {
      font-size: 40px;
      text-align: center;
    }

    .container-welcome p {
      font-size: 14px;
      text-align: center;
    }

    .container-welcome .button {
      border: 2px solid white;
      background-color: transparent;
    }

    .container-welcome .button:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }

    .container.toggle .welcome-sign-in {
      transform: translateX(0);
    }

    .container.toggle .welcome-sign-up {
      transform: translateX(-100%);
    }

    /* Estilos para mensajes de error */
    .alert-error {
      background: #fdd;
      border: 1px solid #f00;
      color: #c00;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      width: 310px; /* MISMO ANCHO */
      font-size: 14px;
      font-weight: 600;
    }

    .alert-success {
      background: #dfd;
      border: 1px solid #0a0;
      color: #080;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      width: 310px; /* MISMO ANCHO */
      font-size: 14px;
      font-weight: 600;
    }

    /* Badge de administrador */
    .admin-badge {
      position: absolute;
      top: 15px;
      right: 15px;
      background: #FF6B1A; /* COLOR NARANJA */
      color: white;
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      z-index: 10;
    }

    /* --- Media Query para Responsividad (Móviles y Tabletas pequeñas) --- */
    @media screen and (max-width: 990px) {
      .container {
        height: auto;
        min-height: 90vh;
        max-width: 500px;
        flex-direction: column;
      }

      .container-form {
        width: 100%;
        padding: 40px 0;
      }

      form {
        padding: 0 20px;
      }

      .container-input,
      .dual-inputs,
      .container-input.multi,
      .msg-pass,
      .length-msg,
      .alert-error,
      .alert-success {
        width: 100%; /* En móvil ocupan el 100% */
        max-width: 310px;
      }

      /* Panel lateral se convierte en panel superior/inferior para móviles */
      .container-welcome {
        position: relative;
        width: 100%;
        height: 250px;
        transform: translateX(0);
        border-radius: 0 0 15px 15px;
        order: -1;
        flex-shrink: 0;
      }

      .container.toggle .container-welcome {
        border-radius: 15px 15px 0 0;
        order: 1;
      }

      .container.toggle {
        flex-direction: column-reverse;
      }

      /* Mostrar ambos formularios siempre en móvil */
      .container-form:nth-child(1),
      .container-form:nth-child(2) {
        display: flex;
      }

      /* Ocultamos las animaciones de transform: translateX para móviles */
      .sign-in,
      .sign-up {
        transform: none !important;
      }

      /* Posicionamiento de bienvenida */
      .container-welcome .welcome {
        position: relative;
        transform: none !important;
        width: 100%;
        padding: 20px;
      }

      /* Mostrar ambos paneles de bienvenida en móvil */
      .welcome-sign-in,
      .welcome-sign-up {
        display: flex !important;
      }

      .container-welcome {
        flex-direction: column;
        gap: 20px;
      }

      .container-welcome .welcome {
        position: relative;
      }

      .alert-error,
      .alert-success {
        max-width: 100%;
        margin: 0 auto 20px auto;
      }

      .admin-badge {
        top: 10px;
        right: 10px;
        font-size: 10px;
      }
    }

    /* Para pantallas muy pequeñas */
    @media screen and (max-width: 480px) {
      .container-welcome h3 {
        font-size: 28px;
      }
      
      .dual-inputs {
        flex-direction: column;
      }
      
      .button {
        width: 100%;
        max-width: 310px;
      }
    }
  </style>
</head>
<body>
    <div class="admin-badge">ADMINISTRADOR</div>
  
  <div class="container">
    <!-- Formulario de Inicio de Sesión para Administradores -->
    <div class="container-form">
      <form class="sign-in" method="POST">
        <input type="hidden" name="form_type" value="login">
        <h2>Iniciar Sesión Adiministrador</h2>

        <div class="social-networks">
          <a href="https://www.facebook.com/login/?locale=es_LA" target="_blank" rel="noopener noreferrer">
            <ion-icon name="logo-facebook"></ion-icon>
          </a>
          <a href="https://workspace.google.com/intl/es-419_mx/gmail/" target="_blank" rel="noopener" referrerpolicy="noopener noreferrer">
            <ion-icon name="logo-google"></ion-icon>
          </a>
          <a href="https://www.icloud.com/" target="_blank" rel="noopener" referrerpolicy="noopener noreferrer">
            <ion-icon name="logo-apple"></ion-icon>
          </a>
        </div>

        <?php if (!empty($error_login)): ?>
          <div class="alert-error">
            <?php echo htmlspecialchars($error_login); ?>
          </div>
        <?php endif; ?>
        
        <span>Acceso exclusivo para administradores</span>

        <div class="container-input">
          <ion-icon name="mail-outline"></ion-icon>
          <input type="email" name="email" placeholder="Correo electrónico" required maxlength="30" 
                 value="<?php echo isset($_POST['form_type']) && $_POST['form_type'] == 'login' ? htmlspecialchars($_POST['email'] ?? '') : ''; ?>">
        </div>
        <div class="container-input">
          <ion-icon name="lock-closed-outline"></ion-icon>
          <input type="password" id="login-password" name="password" placeholder="Contraseña" required minlength="10" maxlength="10">
          <ion-icon class="password-toggle" id="toggle-login" name="eye-outline"></ion-icon>
        </div>
        <div class="length-msg" id="login-length-msg">La contraseña debe tener 10 caracteres</div>
        
        <button class="button" id="btn-login" type="submit">INICIAR SESIÓN</button>
      </form>
    </div>

    <!-- Formulario de Registro de Administradores - TODOS LOS CAMPOS OBLIGATORIOS -->
    <div class="container-form">
      <form class="sign-up" method="POST">
        <input type="hidden" name="form_type" value="register_admin">
        <h2>Registrar Administrador</h2>

        <div class="social-networks">
          <a href="https://www.facebook.com/login/?locale=es_LA" target="_blank" rel="noopener noreferrer">
            <ion-icon name="logo-facebook"></ion-icon>
          </a>
          <a href="https://workspace.google.com/intl/es-419_mx/gmail/" target="_blank" rel="noopener" referrerpolicy="noopener noreferrer">
            <ion-icon name="logo-google"></ion-icon>
          </a>
          <a href="https://www.icloud.com/" target="_blank" rel="noopener" referrerpolicy="noopener noreferrer">
            <ion-icon name="logo-apple"></ion-icon>
          </a>
        </div>

        <?php if (!empty($success_message)): ?>
          <div class="alert-success">
            <?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error_register)): ?>
          <div class="alert-error">
            <?php echo htmlspecialchars($error_register); ?>
          </div>
        <?php endif; ?>
        
        <span>Todos los campos son obligatorios</span>
        
        <!-- NOMBRE COMPLETO -->
        <div class="container-input">
          <ion-icon name="person-outline"></ion-icon>
          <input type="text" name="nombre" placeholder="Nombre(s)" required maxlength="30"
                 class="capitalize-text"
                 pattern="^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*(?:\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*)*$"
                 title="Primera letra mayúscula, resto minúsculas. Ej: Juan o María José"
                 oninput="autoCapitalizeWithSpaces(this)"
                 value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
        </div>
        
        <!-- APELLIDOS -->
        <div class="container-input multi">
          <div class="dual-inputs">
            <div class="input-group">
              <ion-icon name="person-outline"></ion-icon>
              <input type="text" name="primer_apellido" placeholder="Primer apellido" required maxlength="10"
                     class="capitalize-text"
                     pattern="^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+$"
                     title="Primera letra mayúscula, resto minúsculas. Ej: Pérez"
                     oninput="autoCapitalize(this)"
                     value="<?php echo isset($_POST['primer_apellido']) ? htmlspecialchars($_POST['primer_apellido']) : ''; ?>">
            </div>
            <div class="input-group">
              <ion-icon name="person-outline"></ion-icon>
              <input type="text" name="segundo_apellido" placeholder="Segundo apellido" required maxlength="10"
                     class="capitalize-text"
                     pattern="^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+$"
                     title="Primera letra mayúscula, resto minúsculas. Ej: González"
                     oninput="autoCapitalize(this)"
                     value="<?php echo isset($_POST['segundo_apellido']) ? htmlspecialchars($_POST['segundo_apellido']) : ''; ?>">
            </div>
          </div>
        </div>
        
        <!-- CORREO ELECTRÓNICO -->
        <div class="container-input">
          <ion-icon name="mail-outline"></ion-icon>
          <input type="email" name="email" placeholder="Correo electrónico" required maxlength="30"
                 value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>

        <!-- TELÉFONO - AHORA ES OBLIGATORIO -->
        <div class="container-input">
          <ion-icon name="call-outline"></ion-icon>
          <input type="tel" name="telefono" placeholder="Teléfono (10 dígitos)" required maxlength="10"
                 pattern="\d{10}" title="10 dígitos sin espacios"
                 value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
        </div>

        <!-- CONTRASEÑA -->
        <div class="container-input">
          <ion-icon name="lock-closed-outline"></ion-icon>
          <input type="password" id="register-password" name="password" 
                 placeholder="Contraseña" required minlength="10" maxlength="10" 
                 pattern="^(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{10}$"
                 title="10 caracteres, 1 mayúscula, 1 símbolo">
          <ion-icon class="password-toggle" id="toggle-register" name="eye-outline"></ion-icon>
        </div>
        <div class="length-msg" id="register-length-msg">La contraseña debe tener 10 caracteres</div>

        <!-- CONFIRMAR CONTRASEÑA -->
        <div class="container-input">
          <ion-icon name="lock-closed-outline"></ion-icon>
          <input type="password" id="register-password2" name="password2" 
                 placeholder="Confirmar contraseña" required minlength="10" maxlength="10">
          <ion-icon class="password-toggle" id="toggle-register2" name="eye-outline"></ion-icon>
        </div>
        
        <div class="msg-pass" id="msg-pass">Las contraseñas no coinciden</div>

        <button class="button" id="btn-register" type="submit">REGISTRAR</button>
      </form>
    </div>

    <!-- Panel lateral de bienvenida -->
    <div class="container-welcome">
      <div class="welcome-sign-up welcome">
        <h3>¿Nuevo Administrador?</h3>
        <p>Registro exclusivo para nuevos administradores del sistema.</p>
        <button class="button" id="btn-sign-up" type="button">REGISTRAR</button>
      </div>

      <div class="welcome-sign-in welcome">
        <h3>Panel Administrativo</h3>
        <p>Acceso exclusivo para administradores del sistema SAFI Electrónicos.</p>
        <button class="button" id="btn-sign-in" type="button">INICIAR SESIÓN</button>
      </div>
    </div>
  </div>

  <script>
  // FUNCIÓN PARA AUTO-CAPITALIZAR NOMBRES Y APELLIDOS
  function autoCapitalize(input) {
    if (input.value.length === 1) {
      input.value = input.value.toUpperCase();
    } else if (input.value.length > 1) {
      // Si el usuario escribe en mayúsculas, convertir a minúsculas excepto la primera letra
      const firstChar = input.value.charAt(0).toUpperCase();
      const restOfString = input.value.slice(1).toLowerCase();
      input.value = firstChar + restOfString;
    }
  }

  // Función para auto-capitalizar después de un espacio (nombres compuestos)
  function autoCapitalizeWithSpaces(input) {
    const words = input.value.split(' ');
    const capitalizedWords = words.map(word => {
      if (word.length > 0) {
        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
      }
      return word;
    });
    input.value = capitalizedWords.join(' ');
  }

  // Aplicar auto-capitalización a los campos de nombre y apellidos
  document.addEventListener('DOMContentLoaded', function() {
    const nameInputs = document.querySelectorAll('input[name="nombre"], input[name="primer_apellido"], input[name="segundo_apellido"]');
    
    nameInputs.forEach(input => {
      // Para nombre (puede tener espacios)
      if (input.name === 'nombre') {
        input.addEventListener('input', function() {
          autoCapitalizeWithSpaces(this);
        });
      } 
      // Para apellidos (sin espacios)
      else {
        input.addEventListener('input', function() {
          autoCapitalize(this);
        });
      }
      
      // También capitalizar al perder el foco
      input.addEventListener('blur', function() {
        if (input.name === 'nombre') {
          autoCapitalizeWithSpaces(this);
        } else {
          autoCapitalize(this);
        }
      });
    });
  });

  // MOSTRAR / OCULTAR CONTRASEÑA
  const toggleRegister = document.getElementById("toggle-register");
  const toggleRegister2 = document.getElementById("toggle-register2");
  const pass1 = document.getElementById("register-password");
  const pass2 = document.getElementById("register-password2");
  
  if (toggleRegister && pass1) {
    toggleRegister.addEventListener("click", () => {
      pass1.type = pass1.type === "password" ? "text" : "password";
    });
  }

  if (toggleRegister2 && pass2) {
    toggleRegister2.addEventListener("click", () => {
      pass2.type = pass2.type === "password" ? "text" : "password";
    });
  }

  // Mostrar/ocultar para login
  const toggleLogin = document.getElementById("toggle-login");
  const passLogin = document.getElementById("login-password");
  
  if (toggleLogin && passLogin) {
    toggleLogin.addEventListener("click", () => {
      passLogin.type = passLogin.type === "password" ? "text" : "password";
    });
  }

  // VALIDAR LONGITUD DE CONTRASEÑA (LOGIN)
  const loginLengthMsg = document.getElementById("login-length-msg");

  if (passLogin) {
    passLogin.addEventListener("input", () => {
      if (passLogin.value.length !== 10) {
        if (loginLengthMsg) {
          loginLengthMsg.style.color = "red";
          loginLengthMsg.textContent = "Debe tener exactamente 10 caracteres";
        }
      } else {
        if (loginLengthMsg) {
          loginLengthMsg.style.color = "#666";
          loginLengthMsg.textContent = "Longitud correcta";
        }
      }
    });
  }

  // VALIDAR LONGITUD DE CONTRASEÑA (REGISTRO)
  const registerLengthMsg = document.getElementById("register-length-msg");

  if (pass1) {
    pass1.addEventListener("input", () => {
      if (pass1.value.length !== 10) {
        if (registerLengthMsg) {
          registerLengthMsg.style.color = "red";
          registerLengthMsg.textContent = "Debe tener exactamente 10 caracteres";
        }
      } else {
        if (registerLengthMsg) {
          registerLengthMsg.style.color = "#666";
          registerLengthMsg.textContent = "Longitud correcta (10 caracteres)";
        }
        validarPasswords();
      }
    });
  }

  // VALIDAR QUE LAS CONTRASEÑAS COINCIDAN
  const msgPass = document.getElementById("msg-pass");

  function validarPasswords() {
    if (pass1 && pass2 && pass1.value !== "" && pass2.value !== "" && pass1.value !== pass2.value) {
      if (msgPass) {
        msgPass.style.display = "block";
      }
      return false;
    } else {
      if (msgPass) {
        msgPass.style.display = "none";
      }
      return true;
    }
  }

  if (pass1 && pass2) {
    pass1.addEventListener("input", validarPasswords);
    pass2.addEventListener("input", validarPasswords);
  }

  // VALIDACIÓN ANTES DE ENVIAR REGISTRO
  const formRegister = document.querySelector(".sign-up");

  if (formRegister) {
    formRegister.addEventListener("submit", function (e) {
      // Validar formato de nombres y apellidos
      const nombreInput = document.querySelector('input[name="nombre"]');
      const primerApellidoInput = document.querySelector('input[name="primer_apellido"]');
      const segundoApellidoInput = document.querySelector('input[name="segundo_apellido"]');
      
      // Expresiones regulares para validación
      const nombreRegex = /^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*(?:\s[A-ZÁÉÍÓÚÑ][a-záéíóúñ]*)*$/;
      const apellidoRegex = /^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+$/;
      
      // Validar nombre
      if (nombreInput && !nombreRegex.test(nombreInput.value)) {
        e.preventDefault();
        alert("Nombre inválido. Debe comenzar con mayúscula y el resto en minúsculas.\nEjemplo: 'Juan' o 'María José'");
        nombreInput.focus();
        return;
      }
      
      // Validar primer apellido
      if (primerApellidoInput && !apellidoRegex.test(primerApellidoInput.value)) {
        e.preventDefault();
        alert("Primer apellido inválido. Debe comenzar con mayúscula y el resto en minúsculas.\nEjemplo: 'Pérez'");
        primerApellidoInput.focus();
        return;
      }
      
      // Validar segundo apellido
      if (segundoApellidoInput && !apellidoRegex.test(segundoApellidoInput.value)) {
        e.preventDefault();
        alert("Segundo apellido inválido. Debe comenzar con mayúscula y el resto en minúsculas.\nEjemplo: 'González'");
        segundoApellidoInput.focus();
        return;
      }
      
      // Validar que todos los campos estén llenos
      const inputs = formRegister.querySelectorAll('input[required]');
      let allFilled = true;
      
      inputs.forEach(input => {
        if (!input.value.trim()) {
          allFilled = false;
          input.style.borderColor = "red";
          if (input.parentElement && input.parentElement.classList.contains('input-group')) {
            input.parentElement.style.borderColor = "red";
          }
        } else {
          input.style.borderColor = "";
          if (input.parentElement && input.parentElement.classList.contains('input-group')) {
            input.parentElement.style.borderColor = "";
          }
        }
      });
      
      if (!allFilled) {
        e.preventDefault();
        alert("Por favor, complete todos los campos obligatorios (*)");
        return;
      }
      
      // Validar contraseñas
      if (!validarPasswords()) {
        e.preventDefault();
        if (msgPass) {
          msgPass.style.display = "block";
        }
        if (pass2) {
          pass2.focus();
        }
        return;
      }
      
      // Validar longitud de contraseña
      if (pass1 && pass1.value.length !== 10) {
        e.preventDefault();
        if (registerLengthMsg) {
          registerLengthMsg.style.color = "red";
          registerLengthMsg.textContent = "La contraseña debe tener exactamente 10 caracteres";
        }
        if (pass1) {
          pass1.focus();
        }
        return;
      }
      
      // Validar formato de contraseña (mayúscula y símbolo)
      if (pass1) {
        const hasUpperCase = /[A-Z]/.test(pass1.value);
        const hasSymbol = /[^a-zA-Z0-9]/.test(pass1.value);
        
        if (!hasUpperCase || !hasSymbol) {
          e.preventDefault();
          alert("La contraseña debe contener al menos una letra mayúscula y un símbolo (ej: @ # $ % & * + - .)");
          if (pass1) {
            pass1.focus();
          }
          return;
        }
      }
      
      // Validar teléfono (10 dígitos exactos)
      const telefonoInput = document.querySelector("input[name='telefono']");
      if (telefonoInput && !/^\d{10}$/.test(telefonoInput.value)) {
        e.preventDefault();
        alert("El teléfono debe tener exactamente 10 dígitos");
        telefonoInput.focus();
        return;
      }
    });
  }

  // ANIMACIÓN DE PANEL 
  const container = document.querySelector(".container");
  const btnSignUp = document.getElementById("btn-sign-up");
  const btnSignIn = document.getElementById("btn-sign-in");

  if (btnSignUp) {
    btnSignUp.addEventListener("click", () => {
      if (container) {
        container.classList.add("toggle");
      }
    });
  }
  
  if (btnSignIn) {
    btnSignIn.addEventListener("click", () => {
      if (container) {
        container.classList.remove("toggle");
      }
    });
  }

  // Si hay error o éxito, mantener el formulario correspondiente visible
  <?php if (!empty($error_register) || !empty($success_message)): ?>
    document.addEventListener('DOMContentLoaded', function() {
      if (container) {
        container.classList.add("toggle");
      }
    });
  <?php elseif (!empty($error_login)): ?>
    document.addEventListener('DOMContentLoaded', function() {
      if (container) {
        container.classList.remove("toggle");
      }
    });
  <?php endif; ?>

  // Ocultar mensaje de éxito después de 5 segundos
  <?php if (!empty($success_message)): ?>
    setTimeout(function() {
      const successMsg = document.querySelector('.alert-success');
      if (successMsg) {
        successMsg.style.transition = 'opacity 0.5s ease';
        successMsg.style.opacity = '0';
        setTimeout(() => {
          if (successMsg.parentNode) {
            successMsg.parentNode.removeChild(successMsg);
          }
        }, 500);
      }
    }, 5000);
  <?php endif; ?>

  // Validar formulario de login
  const formLogin = document.querySelector(".sign-in");
  if (formLogin) {
    formLogin.addEventListener("submit", function(e) {
      const loginPass = document.getElementById("login-password");
      if (loginPass && loginPass.value.length !== 10) {
        e.preventDefault();
        if (loginLengthMsg) {
          loginLengthMsg.style.color = "red";
          loginLengthMsg.textContent = "Debe tener exactamente 10 caracteres";
        }
        loginPass.focus();
        return;
      }
    });
  }

  // Resaltar campos vacíos al perder foco
  const requiredInputs = document.querySelectorAll('input[required]');
  requiredInputs.forEach(input => {
    input.addEventListener('blur', function() {
      if (!this.value.trim()) {
        this.style.borderColor = "red";
        if (this.parentElement && this.parentElement.classList.contains('input-group')) {
          this.parentElement.style.borderColor = "red";
        }
      } else {
        this.style.borderColor = "";
        if (this.parentElement && this.parentElement.classList.contains('input-group')) {
          this.parentElement.style.borderColor = "";
        }
      }
    });
  });
  </script>
</body>
</html>