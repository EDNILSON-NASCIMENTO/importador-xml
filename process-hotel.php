<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('XML_DIR', __DIR__ . '/xml');

if ($_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
    die("Erro ao enviar arquivo.");
}

$csvFile = $_FILES['csvfile']['tmp_name'];
$handle = fopen($csvFile, 'r');
if (!$handle) die("Erro ao abrir arquivo CSV.");

$headers = fgetcsv($handle, 0, ",");

// Validação mínima (pode ser expandida)
if (!in_array("Handle", $headers)) {
    echo "<h3>Colunas encontradas:</h3><pre>";
    print_r($headers);
    echo "</pre>";
    die("O arquivo CSV não contém a coluna 'Handle' esperada.");
}

$arquivosGerados = [];

while (($row = fgetcsv($handle, 0, ",")) !== false) {
    if (empty($row[0])) continue;

    $registro = array_combine($headers, $row);

    // Exemplo de campos mínimos para hotel — adapte conforme suas colunas reais
    $handleId = strtolower($registro['Handle']);
    $cliente = strtolower($registro['Cliente'] ?? 'não_informado');
    $checkin = $registro['Checkin'] ?? date('d/m/Y');
    $checkout = $registro['Checkout'] ?? date('d/m/Y');
    $cidade = $registro['Cidade'] ?? 'São Paulo';

    // DOM XML
    $dom = new DOMDocument("1.0", "iso-8859-1");
    $dom->formatOutput = true;

    $root = $dom->createElement("bilhetes");
    $dom->appendChild($root);

    $root->appendChild($dom->createElement("nr_arquivo", $handleId));
    $root->appendChild($dom->createElement("data_geracao", date('d/m/Y')));
    $root->appendChild($dom->createElement("hora_geracao", date('H:i')));
    $root->appendChild($dom->createElement("nome_agencia", "uniglobe pro"));
    $root->appendChild($dom->createElement("versao_xml", "4"));

    $reserva = $dom->createElement("reserva_hotel");
    $reserva->appendChild($dom->createElement("cliente", $cliente));
    $reserva->appendChild($dom->createElement("cidade", $cidade));
    $reserva->appendChild($dom->createElement("checkin", $checkin));
    $reserva->appendChild($dom->createElement("checkout", $checkout));
    $root->appendChild($reserva);

    $filename = XML_DIR . '/hotel-' . $handleId . '.xml';
    $dom->save($filename);
    $arquivosGerados[] = $filename;
}

fclose($handle);

// Exibe arquivos gerados
echo "<h3>Arquivos XML gerados:</h3>";
foreach ($arquivosGerados as $arq) {
    echo "<a href='$arq' download>" . basename($arq) . "</a><br>";
}
