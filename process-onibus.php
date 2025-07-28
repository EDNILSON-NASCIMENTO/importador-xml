<?php
while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$db   = 'dadoswintour';
$user = 'root';
$pass = '1234';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    echo json_encode(['error' => "Erro DB: " . $e->getMessage()]);
    exit;
}

function formatarData($data) {
    $parts = explode("/", $data);
    return count($parts) === 3 ? "$parts[2]-" . str_pad($parts[1], 2, "0", STR_PAD_LEFT) . "-" . str_pad($parts[0], 2, "0", STR_PAD_LEFT) : '';
}

function limparValorDecimal($valor) {
    $limpo = str_replace(['R$', ' ', '.', "'"], '', $valor);
    return (trim($limpo) === '' || $limpo === '-') ? 0 : floatval(str_replace(',', '.', $limpo));
}

if (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Erro ao enviar arquivo.']);
    exit;
}

$csvFile = $_FILES['csvfile']['tmp_name'];
$handle = fopen($csvFile, 'r');
if (!$handle) {
    echo json_encode(['error' => 'Erro ao abrir arquivo.']);
    exit;
}

$headers = fgetcsv($handle, 0, ",");
$arquivosGerados = [];

while (($row = fgetcsv($handle, 0, ",")) !== false) {
    if (empty($row[0])) continue;
    $registro = array_combine($headers, $row);

    $requisicao = strtolower($registro['RequisiçãoBenner']);
    $handleId = strtolower($registro['Handle']);
    $localizador = strtolower($registro['MiscelaneosLocalizador']);
    $passageiro = strtolower($registro['PassageiroNomeCompleto']);
    $matricula = strtolower($registro['PassageiroMatrícula']);
    $dataEmissao = formatarData($registro['DataEmissão']);
    $dataIda = formatarData($registro['DataIda']);
    $forma_pgto = strtoupper($registro['FormaPagamento']);
    $emissor = strtolower($registro['Emissor']);
    $cliente = strtolower($registro['InformaçãoCliente']);
    $centro = strtolower($registro['CentroDescritivo']);
    $solicitante = strtolower($registro['Solicitante']);
    $aprovador = strtolower($registro['AprovadorEfetivo']);
    $departamento = strtolower($registro['Departamento']);
    $motivo = strtolower($registro['Finalidade']);
    $just = strtolower($registro['PoliticaJustificativaÔnibus']);
    $valor_total = limparValorDecimal($registro['ValorTotal']);
    $valor_taxas = limparValorDecimal($registro['ValorTaxas']);
    $desconto = 0;

    $stmt = $pdo->prepare("INSERT INTO servicos_wintour (
        tipo_servico, requisicao, handle, localizador, nome_passageiro, matricula,
        data_emissao, data_embarque, forma_pagamento, emissor, cliente,
        ccustos_cliente, solicitante, aprovador, departamento, motivo_viagem,
        justificativa, valor_total, valor_taxas, desconto
    ) VALUES (
        'onibus', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");

    $stmt->execute([
        $requisicao, $handleId, $localizador, $passageiro, $matricula,
        $dataEmissao, $dataIda, $forma_pgto, $emissor, $cliente, $centro,
        $solicitante, $aprovador, $departamento, $motivo, $just,
        $valor_total, $valor_taxas, $desconto
    ]);

    // Geração do XML
    $dom = new DOMDocument("1.0", "iso-8859-1");
    $dom->formatOutput = true;
    $root = $dom->createElement("bilhetes");
    $dom->appendChild($root);
    $root->appendChild($dom->createElement("nr_arquivo", $requisicao));
    $root->appendChild($dom->createElement("data_geracao", date('d/m/Y')));
    $root->appendChild($dom->createElement("hora_geracao", date('H:i')));
    $root->appendChild($dom->createElement("nome_agencia", "uniglobe pro"));
    $root->appendChild($dom->createElement("versao_xml", "4"));

    $bilheteEl = $dom->createElement("bilhete");
    $root->appendChild($bilheteEl);

    $bilheteEl->appendChild($dom->createElement("idv_externo", $handleId));
    $bilheteEl->appendChild($dom->createElement("data_lancamento", date('d/m/Y', strtotime($dataEmissao))));
    $bilheteEl->appendChild($dom->createElement("codigo_produto", "bus"));
    $bilheteEl->appendChild($dom->createElement("fornecedor", "512"));
    $bilheteEl->appendChild($dom->createElement("num_bilhete", $localizador));
    $bilheteEl->appendChild($dom->createElement("prestador_svc", "onibus"));
    $bilheteEl->appendChild($dom->createElement("forma_de_pagamento", strtolower($forma_pgto)));
    $bilheteEl->appendChild($dom->createElement("moeda", "brl"));
    $bilheteEl->appendChild($dom->createElement("emissor", $emissor));
    $bilheteEl->appendChild($dom->createElement("cliente", $cliente));
    $bilheteEl->appendChild($dom->createElement("ccustos_cliente", $centro));
    $bilheteEl->appendChild($dom->createElement("solicitante", $solicitante));
    $bilheteEl->appendChild($dom->createElement("aprovador", $aprovador));
    $bilheteEl->appendChild($dom->createElement("departamento", $departamento));
    $bilheteEl->appendChild($dom->createElement("motivo_viagem", $motivo));
    $bilheteEl->appendChild($dom->createElement("motivo_recusa", ""));
    $bilheteEl->appendChild($dom->createElement("matricula", $matricula));
    $bilheteEl->appendChild($dom->createElement("numero_requisicao", $requisicao));
    $bilheteEl->appendChild($dom->createElement("localizador", $localizador));
    $bilheteEl->appendChild($dom->createElement("passageiro", $passageiro));
    $bilheteEl->appendChild($dom->createElement("tipo_domest_inter", "d"));
    $bilheteEl->appendChild($dom->createElement("tipo_roteiro", "1"));

    $valores = $dom->createElement("valores");
    foreach ([["tarifa", $valor_total], ["taxa", $valor_taxas]] as [$codigo, $valor]) {
        $item = $dom->createElement("item");
        $item->appendChild($dom->createElement("codigo", $codigo));
        $item->appendChild($dom->createElement("valor", number_format($valor, 2, '.', '')));
        $valores->appendChild($item);
    }
    $bilheteEl->appendChild($valores);

    $bilheteEl->appendChild($dom->createElement("info_adicionais", $just));

    $filename = __DIR__ . '/xml/wintour-' . $requisicao . '.xml';
    $dom->save($filename);
    $arquivosGerados[] = basename($filename);

    // Atualiza envios_status.json
    $statusFile = __DIR__ . '/logs/envios_status.json';
    $status = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];
    $status[$arquivosGerados[count($arquivosGerados) - 1]] = date('Y-m-d H:i:s');
    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

fclose($handle);

echo empty($arquivosGerados)
    ? json_encode(['error' => 'Nenhum arquivo foi gerado.'])
    : json_encode(['arquivos' => $arquivosGerados]);

ob_end_clean();
echo json_encode(['arquivos' => $arquivosGerados]);

exit;
