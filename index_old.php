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
    <form action="index.php" method="POST">
        <input type="text" placeholder="username" style="text-align: center;">
        <br>
        <input type="text" placeholder="password" style="text-align: center;">
    </form>
    <br>
    <button type="submit">submit</button>
</div>

<br>
<br>
<table border="1" align='center'>

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

    <?php $query123 = "SELECT email_login FROM usuario WHERE id_usuario = 520";?>
    
    <?=htmlspecialchars($query123)?>
</body>
</html>

<!--
    id_usuario | run_persona | email_login            | clave_encriptada | tipo_usuario
    520 | 24183297-K  | claudio.galdames727@mail.cl   | clave6155        | administrativo
    521 | 10532988-0  | gonzalo.jara204@mail.cl       | clave3724        | administrativo
    522 | 14295834-7  | javiera.espinoza681@mail.cl   | clave7762        | administrativo
    523 | 11383074-5  | felicia.contreras648@mail.cl  | clave8734        | administrativo
    524 | 17311895-4  | oscar.galdames516@mail.cl     | clave7433        | administrativo
-->