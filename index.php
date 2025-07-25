<?php
define('XML_DIR', __DIR__ . '/xml');
$tipos = ['aereo', 'hotel', 'carro', 'onibus'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Importador CSV e Enviar XML para Wintour</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 40px; }
    h2 { margin-top: 40px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 8px 12px; border: 1px solid #ccc; }
    th { background-color: #f5f5f5; }
    input[type="submit"], button {
      margin-top: 15px;
      padding: 10px 20px;
      font-size: 16px;
    }
    .container { max-width: 900px; margin: auto; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Importar CSV e Enviar XML para Wintour</h1>

    <h2>1. Importar CSV</h2>
    <form id="csvForm" enctype="multipart/form-data">
      <label for="tipo">Tipo de importação:</label>
      <select name="tipo" id="tipo" required>
        <?php foreach ($tipos as $tipo): ?>
          <option value="<?= $tipo ?>"><?= ucfirst($tipo) ?></option>
        <?php endforeach; ?>
      </select><br><br>

      <input type="file" name="csvfile" accept=".csv" required>
      <button type="submit">Importar CSV</button>
    </form>

    <div id="csvResultado" style="margin-top: 20px;"></div>

    <h2>2. XMLs disponíveis para envio</h2>
    <form id="envioForm">
      <table>
        <thead>
          <tr>
            <th>Selecionar</th>
            <th>Arquivo XML</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="xmlList">
          <?php require 'xml-lista.php'; ?>
        </tbody>
      </table>
      <button type="submit">🚀 Enviar Selecionados</button>
    </form>

    <form action="baixar-xmls.php" method="post">
      <input type="submit" value="📦 Baixar Todos os XMLs (.zip)">
    </form>

    <p><a href="historico-envios.php">📜 Ver histórico de envios</a></p>
    <div id="status" style="margin-top: 20px;"></div>
  </div>

<script>
document.getElementById('csvForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const form = e.target;
  const formData = new FormData(form);
  const tipo = form.tipo.value;

  fetch(`process-${tipo}.php`, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    const out = document.getElementById('csvResultado');
    if (data.error) {
      out.innerHTML = `<p style="color: red;">Erro: ${data.error}</p>`;
      return;
    }

    // Recarrega a página para atualizar a lista de arquivos
    window.location.reload();
  });
});

document.getElementById('envioForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const checkboxes = [...document.querySelectorAll('input[name="xml_files[]"]:checked')];
  const status = document.getElementById('status');
  if (checkboxes.length === 0) {
    alert('Selecione pelo menos um XML.');
    return;
  }

  status.innerHTML = `<p>Enviando ${checkboxes.length} arquivos...</p><ul id="log"></ul>`;
  const log = document.getElementById('log');

  const enviarArquivo = (index) => {
    if (index >= checkboxes.length) {
      log.innerHTML += `<li><strong>✅ Todos os arquivos foram enviados.</strong></li>`;
      fetch('xml-lista.php')
        .then(r => r.text())
        .then(html => document.getElementById('xmlList').innerHTML = html);
      return;
    }

    const formData = new FormData();
    formData.append('xml_files', checkboxes[index].value);

    fetch('envia-xml.php', {
      method: 'POST',
      body: formData
    })
    .then(r => r.text())
    .then(res => {
      log.innerHTML += `<li><strong>${checkboxes[index].value}</strong>: ${res}</li>`;
      enviarArquivo(index + 1);
    })
    .catch(() => {
      log.innerHTML += `<li><strong>${checkboxes[index].value}</strong>: ❌ Erro</li>`;
      enviarArquivo(index + 1);
    });
  };

  enviarArquivo(0);
});
</script>
</body>
</html>
