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

    // Preparar dados
    $requisicao = strtolower($registro['RequisiçãoBenner']);
    $handleId = strtolower($registro['Handle']);
    $localizador = strtolower($registro['AéreoLocalizador']);
    $passageiro = strtolower($registro['PassageiroNomeCompleto']);
    $matricula = strtolower($registro['PassageiroMatrícula']);
    $cia = strtoupper($registro['CiaAérea']);
    $classe = strtolower($registro['ClasseVoo']);
    $origem = strtolower($registro['AeroportoOrigem']);
    $destino = strtolower($registro['AeroportoDestino']);
    $dataEmissao = formatarData($registro['DataEmissão']);
    $dataEmbarque = formatarData($registro['DataEmbarque']);
    $forma_pgto = strtoupper($registro['FormaPagamento']);
    $emissor = strtolower($registro['Emissor']);
    $cliente = strtolower($registro['InformaçãoCliente']);
    $centro = strtolower($registro['BI']);
    $solicitante = strtolower($registro['Solicitante']);
    $aprovador = strtolower($registro['AprovadorEfetivo']);
    $departamento = strtolower($registro['Departamento']);
    $motivo = strtolower($registro['Finalidade']);
    $recusa = strtolower($registro['PoliticaMotivoAéreo']);
    $just = strtolower($registro['PoliticaJustificativaAéreo']);
    $bilhete = strtolower($registro['Bilhete']);
    $tarifa = limparValorDecimal($registro['TarifaEmitida']);
    $taxas = limparValorDecimal($registro['Taxas']);
    $taxa_du = limparValorDecimal($registro['DescontoAéreo']);

    $prestador = match ($cia) {
        "LATAM" => "la", "AZUL" => "ad", "GOL" => "g3", default => strtolower($cia)
    };
    $forma_pgto_cod = match ($forma_pgto) {
        "CARTAO" => "cc", "FATURADO" => "iv", default => strtolower($forma_pgto)
    };

    // Salva no banco
    $stmt = $pdo->prepare("INSERT INTO servicos_wintour (
        tipo_servico, requisicao, handle, localizador, nome_passageiro, matricula,
        cia_aerea, classe_voo, origem, destino, data_emissao, data_embarque,
        forma_pagamento, emissor, cliente, tarifa, taxas, desconto,
        justificativa, solicitante, aprovador, departamento
    ) VALUES (
        'aereo', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");

    $stmt->execute([
        $requisicao, $handleId, $localizador, $passageiro,
        $matricula, $cia, $classe, $origem, $destino, $dataEmissao, $dataEmbarque,
        $forma_pgto_cod, $emissor, $cliente, $tarifa, $taxas, $taxa_du,
        $just, $solicitante, $aprovador, $departamento
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
    $bilheteEl->appendChild($dom->createElement("codigo_produto", "tkt"));
    $bilheteEl->appendChild($dom->createElement("fornecedor", "512"));
    $bilheteEl->appendChild($dom->createElement("num_bilhete", $bilhete));
    $bilheteEl->appendChild($dom->createElement("prestador_svc", $prestador));
    $bilheteEl->appendChild($dom->createElement("forma_de_pagamento", $forma_pgto_cod));
    $bilheteEl->appendChild($dom->createElement("moeda", "brl"));
    $bilheteEl->appendChild($dom->createElement("emissor", $emissor));
    $bilheteEl->appendChild($dom->createElement("cliente", $cliente));
    $bilheteEl->appendChild($dom->createElement("ccustos_cliente", $centro));
    $bilheteEl->appendChild($dom->createElement("solicitante", $solicitante));
    $bilheteEl->appendChild($dom->createElement("aprovador", $aprovador));
    $bilheteEl->appendChild($dom->createElement("departamento", $departamento));
    $bilheteEl->appendChild($dom->createElement("motivo_viagem", $motivo));
    $bilheteEl->appendChild($dom->createElement("motivo_recusa", $recusa));
    $bilheteEl->appendChild($dom->createElement("matricula", $matricula));
    $bilheteEl->appendChild($dom->createElement("numero_requisicao", $requisicao));
    $bilheteEl->appendChild($dom->createElement("localizador", $localizador));
    $bilheteEl->appendChild($dom->createElement("passageiro", $passageiro));
    $bilheteEl->appendChild($dom->createElement("tipo_domest_inter", "d"));
    $bilheteEl->appendChild($dom->createElement("tipo_roteiro", "1"));

    $valores = $dom->createElement("valores");
    foreach ([["tarifa", $tarifa], ["taxa", $taxas], ["taxa_du", $taxa_du]] as [$codigo, $valor]) {
        $item = $dom->createElement("item");
        $item->appendChild($dom->createElement("codigo", $codigo));
        $item->appendChild($dom->createElement("valor", number_format($valor, 2, '.', '')));
        $valores->appendChild($item);
    }
    $bilheteEl->appendChild($valores);

    $roteiro = $dom->createElement("roteiro");
    $aereo = $dom->createElement("aereo");

    foreach ([["origem"=>$origem,"destino"=>$destino,"hora"=>"08:00","horaChegada"=>"10:00"],
              ["origem"=>$destino,"destino"=>$origem,"hora"=>"19:00","horaChegada"=>"21:00"]] as $t) {
        $trecho = $dom->createElement("trecho");
        $trecho->appendChild($dom->createElement("cia_iata", $prestador));
        $trecho->appendChild($dom->createElement("numero_voo", "-"));
        $trecho->appendChild($dom->createElement("aeroporto_origem", $t["origem"]));
        $trecho->appendChild($dom->createElement("aeroporto_destino", $t["destino"]));
        $trecho->appendChild($dom->createElement("data_partida", date('d/m/Y', strtotime($dataEmbarque))));
        $trecho->appendChild($dom->createElement("hora_partida", $t["hora"]));
        $trecho->appendChild($dom->createElement("data_chegada", date('d/m/Y', strtotime($dataEmbarque))));
        $trecho->appendChild($dom->createElement("hora_chegada", $t["horaChegada"]));
        $trecho->appendChild($dom->createElement("classe", $classe));
        $aereo->appendChild($trecho);
    }

    $roteiro->appendChild($aereo);
    $bilheteEl->appendChild($roteiro);
    $bilheteEl->appendChild($dom->createElement("info_adicionais", $just));

    $filename = __DIR__ . '/xml/wintour-' . $requisicao . '.xml';
    $dom->save($filename);
    $arquivosGerados[] = basename($filename);

    // Atualiza envios_status.json
    $statusFile = __DIR__ . '/logs/envios_status.json';
    $status = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];    
}

fclose($handle);

echo empty($arquivosGerados)
    ? json_encode(['error' => 'Nenhum arquivo foi gerado.'])
    : json_encode(['arquivos' => $arquivosGerados]);

ob_end_clean();
echo json_encode(['arquivos' => $arquivosGerados]);

exit;
