<?php
define('XML_DIR', __DIR__ . '/xml');
define('LOG_DIR', __DIR__ . '/logs');
$statusFile = LOG_DIR . '/envios_status.json';

$statusRaw = file_exists($statusFile) ? file_get_contents($statusFile) : '[]';
$statusList = json_decode($statusRaw, true);
if (!is_array($statusList)) $statusList = [];

$xmlFiles = glob(XML_DIR . '/*.xml');

function buscarStatusArquivo($arquivo, $lista) {
    foreach ($lista as $key => $entry) {
        if ($key === $arquivo && is_array($entry) && isset($entry['data'])) {
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

        $enviado = is_array($status) && isset($status['data']) && isset($status['tipo']) && file_exists(LOG_DIR . '/envio_' . date('Ymd', strtotime($status['data'])) . '.log');
        $dataEnvio = $enviado ? date('d/m/Y H:i', strtotime($status['data'])) : '';
        $link = "xml/" . urlencode($name);

        echo "<tr>
          <td>" . ($enviado ? "✅" : "<input type='checkbox' name='xml_files[]' value='$name'>") . "</td>
          <td><a href=\"$link\" target=\"_blank\">$name</a></td>
          <td>" . ($enviado ? "Enviado em: $dataEnvio (" . strtoupper($status['tipo']) . ")" : "Disponível para envio") . "</td>
        </tr>";
    }
}
