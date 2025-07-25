<?php
$statusFile = __DIR__ . '/logs/envios_status.json';
$status = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];

?><!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Histórico de Envios</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 40px; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
    th { background: #f2f2f2; }
  </style>
</head>
<body>
  <h1>📜 Histórico de Envios de XML</h1>

  <?php if (empty($status)): ?>
    <p>Nenhum envio registrado ainda.</p>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Arquivo XML</th>
          <th>Data de Envio</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($status as $file => $datetime): ?>
          <tr>
            <td><?= htmlspecialchars($file) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($datetime)) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <p><a href="index.php">← Voltar</a></p>
</body>
</html>
