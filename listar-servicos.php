<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = new PDO("mysql:host=localhost;dbname=dadoswintour;charset=utf8", "root", "1234");

$tipos = ['aereo', 'hotel', 'carro', 'onibus'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Serviços Importados</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 30px; }
    h2 { margin-top: 50px; color: #333; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 8px 12px; border: 1px solid #ccc; }
    th { background-color: #f5f5f5; }
    tr:nth-child(even) { background-color: #f9f9f9; }
  </style>
</head>
<body>
  <h1>📋 Serviços Importados (por tipo)</h1>

  <?php foreach ($tipos as $tipo): ?>
    <h2>🗂 <?= ucfirst($tipo) ?></h2>
    <?php
    $stmt = $pdo->prepare("SELECT * FROM servicos_wintour WHERE tipo_servico = ? ORDER BY data_criacao DESC");
    $stmt->execute([$tipo]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (count($rows) === 0): ?>
      <p><em>Nenhum serviço <?= $tipo ?> registrado.</em></p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Requisição</th>
            <th>Nome</th>
            <th>Localizador</th>            
            <th>Embarque / Check-in</th>
            <th>Origem / Cidade</th>
            <th>Destino / Estado</th>
            <th>Valor Total</th>
            <th>Forma Pgto</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= $r['requisicao'] ?></td>
              <td><?= $r['nome_passageiro'] ?? '-' ?></td>
              <td><?= $r['localizador'] ?></td>
              <td>
                <?= $tipo === 'hotel'
                  ? date('d/m/Y', strtotime($r['data_checkin'] ?? ''))
                  : date('d/m/Y', strtotime($r['data_embarque'] ?? '')) ?>
              </td>
              <td><?= $r['origem'] ?? $r['cidade'] ?? '-' ?></td>
              <td><?= $r['destino'] ?? $r['estado'] ?? '-' ?></td>
              <td>R$ <?= number_format($r['valor_total'] ?? $r['tarifa'] + $r['taxas'], 2, ',', '.') ?></td>
              <td><?= strtolower($r['forma_pagamento']) ?></td>
              <td><?= date('d/m/Y H:i', strtotime($r['data_criacao'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endforeach; ?>
</body>
  <div style="margin-top: 40px;">
    <a href="index.php">
      🔙 Voltar para Importação
    </a>
  </div>

</html>
