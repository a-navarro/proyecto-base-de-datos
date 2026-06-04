<?php
require_once 'utils.php';

$db = conectarBD();

$query = "SELECT * FROM persona LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();

$personas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>DCColo</title>
</head>
<body>

<h1 align='center'>DCColo</h1>

<div align='center'>
    <b>LOGIN</b>
    <br>
    <br>
    <form action="" method="post">
        <input type="text" placeholder="username" style="text-align: center;">
    </form>
    
    <form action="" method="post">
        <input type="text" placeholder="password" style="text-align: center;">
    </form>
    <br>
    <button action="print">submit</button>
</div>


<table border="1">

    <tr>
        <th>RUN</th>
        <th>Nombre</th>
        <th>Correo</th>
    </tr>

    <?php foreach ($personas as $persona): ?>
        <tr>
            <td><?= htmlspecialchars($persona['run']) ?></td>
            <td><?= htmlspecialchars($persona['nombre_completo']) ?></td>
            <td><?= htmlspecialchars($persona['email']) ?></td>
        </tr>
    <?php endforeach; ?>

</table>

</body>
</html>