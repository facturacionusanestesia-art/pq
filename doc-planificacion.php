<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Documentación técnica - Planificación quirúrgica</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        body { font-family: system-ui; background:#f5f5f5; margin:0; }
        .container { max-width:900px; margin:2rem auto; background:#fff; padding:2rem; border-radius:8px; }
    </style>
</head>
<body>

<div class="container">
    <div id="md-content">Cargando documentación...</div>
</div>

<?php
$md = file_get_contents('planificacion-quirurgica.md');
?>

<script>
    const raw = <?php echo json_encode($md); ?>;
    document.getElementById('md-content').innerHTML = marked.parse(raw);
</script>

</body>
</html>
