<?php
define('XML_DIR', __DIR__ . '/xml');
define('LOG_DIR', __DIR__ . '/logs');
$statusFile = LOG_DIR . '/envios_status.json';
$status = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];

$xmlFiles = glob(XML_DIR . '/*.xml');
if (empty($xmlFiles)) {
  echo "<tr><td colspan='3'>Nenhum XML encontrado.</td></tr>";
} else {
  foreach ($xmlFiles as $file) {
    $name = basename($file);
    $enviado = isset($status[$name]);
    $dataEnvio = $enviado ? date('d/m/Y H:i', strtotime($status[$name])) : '';
    $link = "xml/" . urlencode($name); // link clicável para abrir XML

    echo "<tr>
      <td>" . ($enviado ? "✅" : "<input type='checkbox' name='xml_files[]' value='$name'>") . "</td>
      <td><a href=\"$link\" target=\"_blank\">$name</a></td>
      <td>" . ($enviado ? "Enviado em: $dataEnvio" : "Disponível para envio") . "</td>
    </tr>";
  }
}
