<?php
session_start();

if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit();
}
if (!isset($_SESSION['id_empresa_actual'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'includes/db_connection.php';
require_once 'includes/header.php';

$id_empresa_actual = $_SESSION['id_empresa_actual'];
$id_usuario_actual = $_SESSION['id_usuario'];
$mensaje = '';

$criterios = [
    1 => 'Crecimiento',
    2 => 'Naturaleza de los competidores',
    3 => 'Posibilidad capacidad productiva',
    4 => 'Rentabilidad modis distributivos',
    5 => 'Diferenciación del producto',
    6 => 'Barreras de salida',
    7 => 'Economías de escala',
    8 => 'Necesidad de capital',
    9 => 'Acceso a la tecnología',
    10 => 'Reglamentos o leyes limitativos',
    11 => 'Trámites burocráticos',
    12 => 'Reacción esperada actuales competidores',
    13 => 'Número de clientes',
    14 => 'Posibilidad de integración ascendente',
    15 => 'Rentabilidad de los clientes',
    16 => 'Costo de cambio de proveedor para cliente',
    17 => 'Disponibilidad de Productos Sustitutivos'
];

$valor_labels = [0 => 'Hostil', 1 => 'Nada', 2 => 'Poco', 3 => 'Medio', 4 => 'Alto', 5 => 'Muy Alto'];

$labels_extremos = [
    1 => ['Lento', 'Rápido'],
    2 => ['Muchos', 'Pocos'],
    3 => ['Sí', 'No'],
    4 => ['Baja', 'Alta'],
    5 => ['Escasa', 'Elevada'],
    6 => ['Bajas', 'Altas'],
    7 => ['No', 'Sí'],
    8 => ['Bajas', 'Altas'],
    9 => ['Fácil', 'Difícil'],
    10 => ['No', 'Sí'],
    11 => ['No', 'Sí'],
    12 => ['Escasa', 'Enérgica'],
    13 => ['Pocos', 'Muchos'],
    14 => ['Pequeña', 'Grande'],
    15 => ['Baja', 'Alta'],
    16 => ['Bajo', 'Alto'],
    17 => ['Grande', 'Pequeña']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mysqli->begin_transaction();
    try {
        $stmt_porter = $mysqli->prepare("INSERT INTO porter_respuestas (id_empresa, criterio_id, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach ($criterios as $id => $descripcion) {
            if (isset($_POST['criterio_' . $id])) {
                $valor = intval($_POST['criterio_' . $id]);
                $stmt_porter->bind_param("iii", $id_empresa_actual, $id, $valor);
                $stmt_porter->execute();
            }
        }
        $stmt_porter->close();

        $foda_items = [
            'oportunidad' => [1 => $_POST['o1'] ?? '', 2 => $_POST['o2'] ?? ''],
            'amenaza' => [1 => $_POST['a1'] ?? '', 2 => $_POST['a2'] ?? '']
        ];
        
        $stmt_delete_foda = $mysqli->prepare("DELETE FROM foda WHERE id_empresa = ? AND id_usuario = ? AND origen = 'porter'");
        $stmt_delete_foda->bind_param("ii", $id_empresa_actual, $id_usuario_actual);
        $stmt_delete_foda->execute();
        $stmt_delete_foda->close();

        $stmt_foda = $mysqli->prepare("INSERT INTO foda (id_empresa, id_usuario, tipo, descripcion, origen, posicion) VALUES (?, ?, ?, ?, 'porter', ?)");
        foreach ($foda_items as $tipo => $items) {
            foreach ($items as $posicion => $descripcion) {
                if (!empty(trim($descripcion))) {
                    $stmt_foda->bind_param("iissi", $id_empresa_actual, $id_usuario_actual, $tipo, $descripcion, $posicion);
                    $stmt_foda->execute();
                }
            }
        }
        $stmt_foda->close();

        $mysqli->commit();
        $mensaje = 'Análisis guardado correctamente.';
    } catch (Exception $e) {
        $mysqli->rollback();
        $mensaje = 'Error al guardar el análisis: ' . $e->getMessage();
    }
}

$respuestas_guardadas = [];
$stmt_load = $mysqli->prepare("SELECT criterio_id, valor FROM porter_respuestas WHERE id_empresa = ?");
$stmt_load->bind_param("i", $id_empresa_actual);
$stmt_load->execute();
$result = $stmt_load->get_result();
while ($row = $result->fetch_assoc()) {
    $respuestas_guardadas[$row['criterio_id']] = $row['valor'];
}
$stmt_load->close();

$foda_guardado = ['o1' => '', 'o2' => '', 'a1' => '', 'a2' => ''];
$stmt_foda_load = $mysqli->prepare("SELECT tipo, descripcion, posicion FROM foda WHERE id_empresa = ? AND id_usuario = ? AND origen = 'porter'");
$stmt_foda_load->bind_param("ii", $id_empresa_actual, $id_usuario_actual);
$stmt_foda_load->execute();
$result_foda = $stmt_foda_load->get_result();
while ($row = $result_foda->fetch_assoc()) {
    if ($row['tipo'] == 'oportunidad' && $row['posicion'] == 1) $foda_guardado['o1'] = $row['descripcion'];
    if ($row['tipo'] == 'oportunidad' && $row['posicion'] == 2) $foda_guardado['o2'] = $row['descripcion'];
    if ($row['tipo'] == 'amenaza' && $row['posicion'] == 1) $foda_guardado['a1'] = $row['descripcion'];
    if ($row['tipo'] == 'amenaza' && $row['posicion'] == 2) $foda_guardado['a2'] = $row['descripcion'];
}
$stmt_foda_load->close();

$total_score = 0;
foreach ($respuestas_guardadas as $valor) {
    $total_score += $valor;
}

$conclusion = '';
if ($total_score == 0 && count($respuestas_guardadas) < count($criterios)) {
     $conclusion = 'Complete el análisis para ver la conclusión.';
} else if ($total_score < 30) {
    $conclusion = "Estamos en un mercado altamente competitivo, en el que es muy difícil hacerse un hueco en el mercado.";
} else if ($total_score < 45) {
    $conclusion = "El mercado presenta competitividad moderada con algunas oportunidades.";
} else if ($total_score < 60) {
    $conclusion = "La situación actual del mercado es favorable a la empresa.";
} else {
    $conclusion = "El mercado es altamente favorable y presenta excelentes oportunidades de crecimiento.";
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Análisis Porter</title>
</head>
<body>
    <div style="width: 90%; margin: 20px auto;">
        <h1>8. ANÁLISIS PORTER</h1>
        <p>A continuación marque con una X en las casillas que estime conveniente según el estado actual de su empresa. Valore su perfil competitivo en la escala Hostil-Favorable. Al finalizar lea la conclusión, para su caso particular, relativa al análisis del entorno próximo.</p>
        
        <?php if ($mensaje) echo "<p style='color: green; font-weight: bold;'>$mensaje</p>"; ?>

        <form action="porter_5fuerzas.php" method="POST">
            <table border="1" style="width:100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 0.9em;">
                <thead style="background-color: #f0f0f0;">
                    <tr>
                        <th style="padding: 8px; width: 30%;">PERFIL COMPETITIVO</th>
                        <th style="padding: 8px; width: 10%;">Hostil</th>
                        <th style="padding: 8px;">Nada (0)</th>
                        <th style="padding: 8px;">Poco (1)</th>
                        <th style="padding: 8px;">Medio (2)</th>
                        <th style="padding: 8px;">Alto (3)</th>
                        <th style="padding: 8px;">Muy Alto (4)</th>
                        <th style="padding: 8px;">Favorable (5)</th>
                        <th style="padding: 8px; width: 10%;">Favorable</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background-color: #e0e0e0;">
                        <td colspan="9" style="padding: 8px; font-weight: bold;">Finalidad empresas del sector</td>
                    </tr>
                    <?php 
                    $section1_ids = [1, 2, 3, 4, 5, 6];
                    foreach ($section1_ids as $id): 
                        $valor_guardado = $respuestas_guardadas[$id] ?? -1;
                    ?>
                    <tr>
                        <td style="padding: 8px;"><?php echo $criterios[$id]; ?></td>
                        <td style="padding: 8px; color: red; text-align: center;"><?php echo $labels_extremos[$id][0]; ?></td>
                        <?php foreach ($valor_labels as $val_id => $label): ?>
                        <td style="text-align:center; padding: 8px;">
                            <input type="radio" name="criterio_<?php echo $id; ?>" value="<?php echo $val_id; ?>" <?php echo ($valor_guardado == $val_id) ? 'checked' : ''; ?> required>
                        </td>
                        <?php endforeach; ?>
                        <td style="padding: 8px; color: blue; text-align: center;"><?php echo $labels_extremos[$id][1]; ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr style="background-color: #e0e0e0;">
                        <td colspan="9" style="padding: 8px; font-weight: bold;">Barreras de Entrada</td>
                    </tr>
                    <?php 
                    $section2_ids = [7, 8, 9, 10, 11, 12];
                    foreach ($section2_ids as $id): 
                        $valor_guardado = $respuestas_guardadas[$id] ?? -1;
                    ?>
                    <tr>
                        <td style="padding: 8px;"><?php echo $criterios[$id]; ?></td>
                        <td style="padding: 8px; color: red; text-align: center;"><?php echo $labels_extremos[$id][0]; ?></td>
                        <?php foreach ($valor_labels as $val_id => $label): ?>
                        <td style="text-align:center; padding: 8px;">
                            <input type="radio" name="criterio_<?php echo $id; ?>" value="<?php echo $val_id; ?>" <?php echo ($valor_guardado == $val_id) ? 'checked' : ''; ?> required>
                        </td>
                        <?php endforeach; ?>
                        <td style="padding: 8px; color: blue; text-align: center;"><?php echo $labels_extremos[$id][1]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background-color: #e0e0e0;">
                        <td colspan="9" style="padding: 8px; font-weight: bold;">Poder de los Clientes</td>
                    </tr>
                    <?php 
                    $section3_ids = [13, 14, 15, 16];
                    foreach ($section3_ids as $id): 
                        $valor_guardado = $respuestas_guardadas[$id] ?? -1;
                    ?>
                    <tr>
                        <td style="padding: 8px;"><?php echo $criterios[$id]; ?></td>
                        <td style="padding: 8px; color: red; text-align: center;"><?php echo $labels_extremos[$id][0]; ?></td>
                        <?php foreach ($valor_labels as $val_id => $label): ?>
                        <td style="text-align:center; padding: 8px;">
                            <input type="radio" name="criterio_<?php echo $id; ?>" value="<?php echo $val_id; ?>" <?php echo ($valor_guardado == $val_id) ? 'checked' : ''; ?> required>
                        </td>
                        <?php endforeach; ?>
                        <td style="padding: 8px; color: blue; text-align: center;"><?php echo $labels_extremos[$id][1]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background-color: #e0e0e0;">
                        <td colspan="9" style="padding: 8px; font-weight: bold;">Productos sustitutivos</td>
                    </tr>
                    <?php 
                    $section4_ids = [17];
                    foreach ($section4_ids as $id): 
                        $valor_guardado = $respuestas_guardadas[$id] ?? -1;
                    ?>
                    <tr>
                        <td style="padding: 8px;"><?php echo $criterios[$id]; ?></td>
                        <td style="padding: 8px; color: red; text-align: center;"><?php echo $labels_extremos[$id][0]; ?></td>
                        <?php foreach ($valor_labels as $val_id => $label): ?>
                        <td style="text-align:center; padding: 8px;">
                            <input type="radio" name="criterio_<?php echo $id; ?>" value="<?php echo $val_id; ?>" <?php echo ($valor_guardado == $val_id) ? 'checked' : ''; ?> required>
                        </td>
                        <?php endforeach; ?>
                        <td style="padding: 8px; color: blue; text-align: center;"><?php echo $labels_extremos[$id][1]; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 20px; border: 1px solid black; padding: 10px; background-color: #f9f9f9;">
                <h3 style="margin: 0 0 10px 0;">CONCLUSIÓN</h3>
                <p style="font-size: 1.1em; font-weight: bold; margin: 0 0 10px 0;"><?php echo $conclusion; ?></p>
                <div style="font-size: 1.2em; font-weight: bold; color: #005a9e; text-align: right;">
                    Total: <?php echo $total_score; ?>
                </div>
            </div>

            <p style="font-size: 0.9em; color: #555; margin-top: 15px;">Una vez analizado el entorno próximo de su empresa, es decir, análisis externo de su microentorno, identifique las oportunidades y amenazas más relevantes que desee que se reflejen en el análisis</p>

            <table border="1" style="width:100%; border-collapse: collapse; margin-top: 10px;">
                <tr style="background-color: #ffe0b2;">
                    <th style="padding: 8px;">OPORTUNIDADES</th>
                </tr>
                <tr>
                    <td style="padding: 8px;">O1: <input type="text" name="o1" value="<?php echo htmlspecialchars($foda_guardado['o1']); ?>" style="width: 95%;"></td>
                </tr>
                <tr>
                    <td style="padding: 8px;">O2: <input type="text" name="o2" value="<?php echo htmlspecialchars($foda_guardado['o2']); ?>" style="width: 95%;"></td>
                </tr>
            </table>

            <table border="1" style="width:100%; border-collapse: collapse; margin-top: 10px;">
                <tr style="background-color: #e3f2fd;">
                    <th style="padding: 8px;">AMENAZAS</th>
                </tr>
                <tr>
                    <td style="padding: 8px;">A1: <input type="text" name="a1" value="<?php echo htmlspecialchars($foda_guardado['a1']); ?>" style="width: 95%;"></td>
                </tr>
                <tr>
                    <td style="padding: 8px;">A2: <input type="text" name="a2" value="<?php echo htmlspecialchars($foda_guardado['a2']); ?>" style="width: 95%;"></td>
                </tr>
            </table>

            <div style="margin-top: 20px;">
                <button type="submit" style="padding: 10px 20px; font-size: 1em; background-color: #007bff; color: white; border: none; cursor: pointer;">Guardar Análisis</button>
            </div>
        </form>

        <div style="margin-top: 20px; display: flex; justify-content: space-between;">
            <a href="matriz_bcg.php" style="padding: 8px 15px; background-color: #6c757d; color: white; text-decoration: none;">&laquo; Anterior: Matriz BCG</a>
            <a href="dashboard.php" style="padding: 8px 15px; background-color: #f0f0f0; color: #333; border: 1px solid #ccc; text-decoration: none;">Volver al Índice</a>
            <a href="analisis_pest.php" style="padding: 8px 15px; background-color: #28a745; color: white; text-decoration: none;">Siguiente: PEST &raquo;</a>
        </div>
    </div>
</body>
</html>