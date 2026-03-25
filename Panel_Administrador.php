<?php
require_once 'config.php';
verificarAdmin();
$admin = obtenerAdminActual();

// Obtener estadísticas
$stats_queries = [
    'productos' => "SELECT COUNT(*) as total FROM productos WHERE activo = TRUE",
    'pedidos_hoy' => "SELECT COUNT(*) as total FROM pedidos WHERE DATE(fecha_pedido) = CURDATE()",
    'clientes' => "SELECT COUNT(*) as total FROM clientes",
    'empleados' => "SELECT COUNT(*) as total FROM empleados WHERE activo = TRUE",
    'ingreso_mes' => "SELECT COALESCE(SUM(total), 0) as total FROM pedidos WHERE MONTH(fecha_pedido) = MONTH(CURDATE()) AND YEAR(fecha_pedido) = YEAR(CURDATE()) AND estado = 'Entregado'",
    'proveedores' => "SELECT COUNT(*) as total FROM proveedores WHERE estado_proveedor = 'Activo'"
];

$stats = [];
foreach ($stats_queries as $key => $query) {
    $result = $conn->query($query);
    $row = $result->fetch_assoc();
    $stats[$key] = $key === 'ingreso_mes' ? '$' . number_format($row['total'], 2) : $row['total'];
}

// Obtener productos con stock bajo
$sql_stock_bajo = "SELECT * FROM productos WHERE stock < 10 AND activo = TRUE ORDER BY stock ASC LIMIT 5";
$productos_bajo = $conn->query($sql_stock_bajo);

// Obtener pedidos recientes
$sql_pedidos = "SELECT p.*, CONCAT(c.nombre, ' ', c.primer_apellido) as cliente_nombre 
                FROM pedidos p 
                JOIN clientes c ON p.cliente_id = c.cliente_id 
                ORDER BY p.fecha_pedido DESC LIMIT 5";
$pedidos_recientes = $conn->query($sql_pedidos);

// Obtener empleados recientes
$sql_empleados = "SELECT * FROM empleados WHERE activo = TRUE ORDER BY fecha_creacion DESC LIMIT 4";
$empleados_recientes = $conn->query($sql_empleados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - SAFI Electrónicos</title>
    <style>
        /* ESTILOS GENERALES */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
        }
        
        /* BARRA SUPERIOR */
        .top-navbar {
            background: linear-gradient(135deg, #e25412ff 0%, #b44902ff 100%);
            color: white;
            padding: 0 30px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
        }
        
        .navbar-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .navbar-logo img {
            height: 45px;
            width: auto;
        }
        
        .brand-name {
            font-size: 20px;
            font-weight: 600;
            color: white;
        }
        
        /* MENÚ HORIZONTAL */
        .navbar-menu {
            display: flex;
            gap: 2px;
            flex: 1;
            justify-content: center;
        }
        
        .navbar-menu a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 12px 12px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .navbar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .navbar-menu a.active {
            background: #e74c3c;
            color: white;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        /* USUARIO Y CERRAR SESIÓN */
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 500;
            font-size: 14px;
            color: white;
        }
        
        .user-role {
            font-size: 12px;
            color: rgba(255,255,255,0.7);
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 8px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .logout-btn:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        /* CONTENIDO PRINCIPAL */
        .main-content {
            margin-top: 70px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }
        
        /* HEADER DE PÁGINA */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eaeaea;
        }
        
        .page-header h1 {
            color: #2c3e50;
            font-size: 28px;
        }
        
        /* ESTADÍSTICAS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .stat-icon.productos { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-icon.pedidos { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .stat-icon.clientes { background: linear-gradient(135deg, #e67e22, #d35400); }
        .stat-icon.empleados { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-icon.ingresos { background: linear-gradient(135deg, #1abc9c, #16a085); }
        .stat-icon.proveedores { background: linear-gradient(135deg, #f1c40f, #f39c12); }
        
        .stat-card h3 {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }
        
        /* CONTENIDO PRINCIPAL */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f2f6;
        }
        
        .card-header h3 {
            color: #2c3e50;
            font-size: 18px;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-view:hover {
            background: #2980b9;
        }
        
        /* TABLAS */
        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .dashboard-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .dashboard-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            color: #495057;
        }
        
        .dashboard-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* LISTAS */
        .pedidos-list {
            list-style: none;
        }
        
        .pedido-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .pedido-item:last-child {
            border-bottom: none;
        }
        
        .pedido-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .pedido-cliente {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .pedido-detalle {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .pedido-estado {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .estado-entregado {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .estado-pendiente {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .estado-enviado {
            background: #e8f4fc;
            color: #3498db;
        }
        
        /* EMPLEADOS */
        .empleados-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .empleado-mini {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .empleado-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .empleado-info h4 {
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .empleado-info p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        /* ACCIONES RÁPIDAS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            border-color: #3498db;
            transform: translateY(-2px);
        }
        
        .action-icon {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }
        
        .action-text {
            font-size: 12px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        /* ADMIN BADGE */
        .admin-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        /* RESPONSIVE */
        @media (max-width: 1024px) {
            .navbar-menu {
                gap: 1px;
            }
            
            .navbar-menu a {
                padding: 10px 15px;
                font-size: 14px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .top-navbar {
                padding: 0 15px;
                height: 60px;
            }
            
            .navbar-logo img {
                height: 35px;
            }
            
            .brand-name {
                display: none;
            }
            
            .navbar-menu {
                display: none;
            }
            
            .main-content {
                margin-top: 60px;
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- BARRA SUPERIOR -->
    <div class="top-navbar">
        <!-- LOGO Y NOMBRE -->
        <div class="navbar-logo">
            <?php
                $logo_paths = [
                    'assets/logo.png',
                    'img/logo.png',
                    'logo.png',
                    'Img/logo.png',
                    '../Img/logo.png'
                ];
                
                $logo_found = false;
                foreach ($logo_paths as $path) {
                    if (file_exists($path)) {
                        echo '<img src="' . $path . '" alt="Logo Tienda Electrónica">';
                        $logo_found = true;
                        break;
                    }
                }
                
                if (!$logo_found) {
                    echo '<div style="font-size: 24px;">🏪</div>';
                }
            ?>
        </div>
        
        <!-- MENÚ DE NAVEGACIÓN -->
        <nav class="navbar-menu">
            <a href="admin_dashboard.php">
                <span>📊</span> Inicio
            </a>
            <a href="admin_productos.php" class="active">
                <span>📱</span> Productos
            </a>
            <a href="admin_pedidos.php">
                <span>📦</span> Pedidos
            </a>
            <a href="admin_clientes.php">
                <span>👥</span> Clientes
            </a>
            <a href="admin_empleados.php">
                <span>👔</span> Empleados
            </a>
            <a href="admin_inventario.php">
                <span>📋</span> Inventario
            </a>
            <a href="admin_proveedores.php">
                <span>🏢</span> Proveedores
            </a>
            <a href="admin_reportes.php">
                <span>📈</span> Reportes
            </a>
        </nav>
        
        <!-- USUARIO Y CERRAR SESIÓN -->
        <div class="navbar-user">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($admin['nombre'] . ' ' . $admin['primer_apellido']); ?></div>
                <div class="user-role">Administrador</div>
            </div>
            
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">
                    <span></span> Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
    
    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-content">
        <!-- HEADER DE LA PÁGINA -->
        <div class="page-header">
            <h1>📊 Panel Administrativo</h1>
            <p style="color: #7f8c8d;">Fecha: <?php echo date('d/m/Y'); ?></p>
        </div>
        
        <!-- ESTADÍSTICAS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon productos">📱</div>
                <h3>Productos Activos</h3>
                <div class="number"><?php echo $stats['productos']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pedidos">📦</div>
                <h3>Pedidos Hoy</h3>
                <div class="number"><?php echo $stats['pedidos_hoy']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon clientes">👥</div>
                <h3>Clientes</h3>
                <div class="number"><?php echo $stats['clientes']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon empleados">👔</div>
                <h3>Empleados Activos</h3>
                <div class="number"><?php echo $stats['empleados']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ingresos">💰</div>
                <h3>Ingresos del Mes</h3>
                <div class="number"><?php echo $stats['ingreso_mes']; ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon proveedores">🏢</div>
                <h3>Proveedores Activos</h3>
                <div class="number"><?php echo $stats['proveedores']; ?></div>
            </div>
        </div>
        
        <!-- CONTENIDO PRINCIPAL -->
        <div class="content-grid">
            <!-- PRODUCTOS CON STOCK BAJO -->
            <div class="content-card">
                <div class="card-header">
                    <h3>⚠️ Productos con Stock Bajo</h3>
                    <a href="admin_productos.php" class="btn-view">Ver todos</a>
                </div>
                
                <?php if ($productos_bajo->num_rows > 0): ?>
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Stock</th>
                                <th>Precio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($producto = $productos_bajo->fetch_assoc()): 
                                $badge_class = $producto['stock'] < 5 ? 'bg-danger' : 'bg-warning';
                                $badge_text = $producto['stock'] < 5 ? 'Crítico' : 'Bajo';
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td><?php echo $producto['stock']; ?></td>
                                <td>$<?php echo number_format($producto['precio'], 2); ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #7f8c8d; text-align: center; padding: 30px;">✅ Todos los productos tienen stock suficiente</p>
                <?php endif; ?>
            </div>
            
            <!-- PEDIDOS RECIENTES Y ACCIONES RÁPIDAS -->
            <div>
                <!-- PEDIDOS RECIENTES -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>📦 Pedidos Recientes</h3>
                        <a href="admin_pedidos.php" class="btn-view">Ver todos</a>
                    </div>
                    
                    <?php if ($pedidos_recientes->num_rows > 0): ?>
                        <ul class="pedidos-list">
                            <?php while ($pedido = $pedidos_recientes->fetch_assoc()): 
                                $estado_class = 'estado-pendiente';
                                if ($pedido['estado'] == 'Entregado') $estado_class = 'estado-entregado';
                                elseif ($pedido['estado'] == 'Enviado') $estado_class = 'estado-enviado';
                            ?>
                            <li class="pedido-item">
                                <div class="pedido-info">
                                    <div>
                                        <div class="pedido-cliente">#<?php echo $pedido['pedido_id']; ?> - <?php echo htmlspecialchars($pedido['cliente_nombre']); ?></div>
                                        <div class="pedido-detalle">$<?php echo number_format($pedido['total'], 2); ?> • <?php echo date('d/m H:i', strtotime($pedido['fecha_pedido'])); ?></div>
                                    </div>
                                    <span class="pedido-estado <?php echo $estado_class; ?>"><?php echo $pedido['estado']; ?></span>
                                </div>
                            </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #7f8c8d; text-align: center; padding: 20px;">No hay pedidos recientes</p>
                    <?php endif; ?>
                </div>
                
                <!-- EMPLEADOS RECIENTES -->
                <div class="content-card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h3>👔 Empleados Recientes</h3>
                        <a href="admin_empleados.php" class="btn-view">Ver todos</a>
                    </div>
                    
                    <?php if ($empleados_recientes->num_rows > 0): ?>
                        <div class="empleados-grid">
                            <?php while ($empleado = $empleados_recientes->fetch_assoc()): 
                                $iniciales = substr($empleado['nombre'], 0, 1) . substr($empleado['primer_apellido'], 0, 1);
                            ?>
                            <div class="empleado-mini">
                                <div class="empleado-avatar">
                                    <?php echo strtoupper($iniciales); ?>
                                </div>
                                <div class="empleado-info">
                                    <h4><?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['primer_apellido']); ?></h4>
                                    <p><?php echo htmlspecialchars($empleado['puesto']); ?></p>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #7f8c8d; text-align: center; padding: 20px;">No hay empleados registrados</p>
                    <?php endif; ?>
                </div>
                
                <!-- ACCIONES RÁPIDAS -->
                <div class="content-card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h3>⚡ Acciones Rápidas</h3>
                    </div>
                    <div class="quick-actions">
                        <a href="admin_nuevo_producto.php" class="action-btn">
                            <span class="action-icon">📱</span>
                            <span class="action-text">Nuevo Producto</span>
                        </a>
                        <a href="admin_nuevo_empleado.php" class="action-btn">
                            <span class="action-icon">👔</span>
                            <span class="action-text">Nuevo Empleado</span>
                        </a>
                        <a href="admin_nuevo_pedido.php" class="action-btn">
                            <span class="action-icon">📦</span>
                            <span class="action-text">Nuevo Pedido</span>
                        </a>
                        <a href="admin_generar_reporte.php" class="action-btn">
                            <span class="action-icon">📊</span>
                            <span class="action-text">Generar Reporte</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>