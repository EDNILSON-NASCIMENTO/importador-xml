<?php
define('XML_DIR', __DIR__ . '/xml');
define('LOG_DIR', __DIR__ . '/logs');
$statusFile = LOG_DIR . '/envios_status.json';

$statusRaw = file_exists($statusFile) ? file_get_contents($statusFile) : '[]';
$statusList = json_decode($statusRaw, true);
if (!is_array($statusList)) $statusList = [];

$xmlFiles = glob(XML_DIR . '/*.xml');

function buscarStatusArquivo($arquivo, $lista) {
    foreach ($lista as $entry) {
        if (isset($entry['arquivo']) && $entry['arquivo'] === $arquivo) {
            return $entry;
        }
    }
    return null;
}

if (empty($xmlFiles)) {
    echo "<tr><td colspan='3'>Nenhum XML encontrado.</td></tr>";
} else {
    foreach ($xmlFiles as $file) {
        $name = basename($file);
        $status = buscarStatusArquivo($name, $statusList);

        $enviado = $status !== null;
        $dataEnvio = $enviado ? date('d/m/Y H:i', strtotime($status['data'])) : '';
        $link = "xml/" . urlencode($name);

        echo "<tr>
          <td>" . ($enviado ? "✅" : "<input type='checkbox' name='xml_files[]' value='$name'>") . "</td>
          <td><a href=\"$link\" target=\"_blank\">$name</a></td>
          <td>" . ($enviado ? "Enviado em: $dataEnvio (" . strtoupper($status['tipo']) . ")" : "Disponível para envio") . "</td>
        </tr>";
    }
}
