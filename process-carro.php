<?php
define('XML_DIR', __DIR__ . '/xml');

header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

// Conexão com banco
$host = 'localhost';
$db = 'dadoswintour';
$user = 'root';
$pass = '1234';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    ob_end_clean();
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
    ob_end_clean();
    echo json_encode(['error' => 'Erro ao enviar arquivo.']);
    exit;
}

$csvFile = $_FILES['csvfile']['tmp_name'];
$handle = fopen($csvFile, 'r');
if (!$handle) {
    ob_end_clean();
    echo json_encode(['error' => 'Erro ao abrir arquivo.']);
    exit;
}

$headers = fgetcsv($handle, 0, ",");
$arquivosGerados = [];

while (($row = fgetcsv($handle, 0, ",")) !== false) {
    if (empty($row[0])) continue;
    $registro = array_combine($headers, $row);

    // Processa os dados (igual ao seu script original)
    $requisicao = strtolower($registro['RequisiçãoBenner']);
    $handleId = strtolower($registro['Handle']);
    $localizador = strtolower($registro['VeículoLocalizador']);
    $passageiro = strtolower($registro['PassageiroVeículoNomeCompleto']);
    $matricula = strtolower($registro['PassageiroVeículoMátricula']);
    $cidadeRet = strtolower($registro['LocadoraCidadeRetirada']);
    $cidadeDev = strtolower($registro['LocadoraCidadeDevolução']);
    $localRet = strtolower($registro['Locadora']);
    $localDev = strtolower($registro['Locadora']);
    $checkin = formatarData($registro['DataRetirada']);
    $checkout = formatarData($registro['DataDevolução']);
    $horaRet = "10:00";
    $horaDev = "10:00";
    $categoria = "intermediario";
    $formaPgto = strtolower($registro['FormaPagamento']) === 'INVOICE' ? 'iv' : 'cc';
    $justificativa = strtolower($registro['PoliticaJustificativaVeículo']);
    $solicitante = strtolower($registro['Solicitante']);
    $aprovador = strtolower($registro['AprovadorEfetivo']);
    $departamento = strtolower($registro['Departamento']);
    $cliente = strtolower($registro['InformaçãoCliente']);
    $centroDescritivo = strtolower($registro['BI']);
    $emissor = strtolower($registro['Emissor']);
    $motivoViagem = strtolower($registro['Finalidade']);
    $motivoRecusa = strtolower($registro['PoliticaMotivoVeículo']);
    $dataEmissao = formatarData($registro['DataEmissão']);
    $diarias = (int) $registro['QtddeDiária'];
    $valorDiaria = limparValorDecimal($registro['ValordaDiaria']);
    $valorTotal = limparValorDecimal($registro['ValorTotalDiarias']);
    $valorTaxas = limparValorDecimal($registro['TotalTaxas']);

    // Banco
    $stmt = $pdo->prepare("INSERT INTO servicos_wintour (
        tipo_servico, requisicao, handle, localizador, nome_passageiro, matricula,
        cidade, estado, pais, data_checkin, data_checkout, qtd_diarias, valor_diaria,
        valor_total, valor_taxas, forma_pagamento, justificativa, solicitante, aprovador,
        departamento, cliente, ccustos_cliente, emissor,origem, destino
    ) VALUES (
        'carro', ?, ?, ?, ?, ?, ?, '', 'brasil', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )");

    $stmt->execute([
        $requisicao, $handleId, $localizador, $passageiro, $matricula, $cidadeRet,
        $checkin, $checkout, $diarias, $valorDiaria, $valorTotal, $valorTaxas,
        $formaPgto, $justificativa, $solicitante, $aprovador, $departamento,
        $cliente, $centroDescritivo, $emissor, $origem = $localRet, $destino = $localDev
    ]);

    // XML
    $dom = new DOMDocument("1.0", "utf-8");
    $dom->formatOutput = true;
    $root = $dom->createElement("bilhetes");
    $dom->appendChild($root);
    $root->appendChild($dom->createElement("nr_arquivo", $requisicao));
    $root->appendChild($dom->createElement("data_geracao", date('d/m/Y')));
    $root->appendChild($dom->createElement("hora_geracao", date('H:i')));
    $root->appendChild($dom->createElement("nome_agencia", "uniglobe pro"));
    $root->appendChild($dom->createElement("versao_xml", "4"));

    $bilhete = $dom->createElement("bilhete");
    $root->appendChild($bilhete);
    $bilhete->appendChild($dom->createElement("idv_externo", $handleId));
    $bilhete->appendChild($dom->createElement("data_lancamento", date('d/m/Y', strtotime($dataEmissao))));
    $bilhete->appendChild($dom->createElement("codigo_produto", "car"));
    $bilhete->appendChild($dom->createElement("fornecedor", "512"));
    $bilhete->appendChild($dom->createElement("num_bilhete", strtoupper($localizador)));
    $bilhete->appendChild($dom->createElement("prestador_svc", $localRet));
    $bilhete->appendChild($dom->createElement("forma_de_pagamento", $formaPgto));
    $bilhete->appendChild($dom->createElement("moeda", "brl"));
    $bilhete->appendChild($dom->createElement("emissor", $emissor));
    $bilhete->appendChild($dom->createElement("cliente", $cliente));
    $bilhete->appendChild($dom->createElement("ccustos_cliente", $centroDescritivo));
    $bilhete->appendChild($dom->createElement("solicitante", $solicitante));
    $bilhete->appendChild($dom->createElement("aprovador", $aprovador));
    $bilhete->appendChild($dom->createElement("departamento", $departamento));
    $bilhete->appendChild($dom->createElement("motivo_viagem", $motivoViagem));
    $bilhete->appendChild($dom->createElement("motivo_recusa", $motivoRecusa));
    $bilhete->appendChild($dom->createElement("matricula", $matricula));
    $bilhete->appendChild($dom->createElement("numero_requisicao", $requisicao));
    $bilhete->appendChild($dom->createElement("localizador", $localizador));
    $bilhete->appendChild($dom->createElement("passageiro", $passageiro));
    $bilhete->appendChild($dom->createElement("tipo_domest_inter", "d"));
    $bilhete->appendChild($dom->createElement("tipo_roteiro", "3"));

    $valores = $dom->createElement("valores");
    foreach ([["tarifa", $valorDiaria], ["taxa", $valorTaxas], ["taxa_du", 0.00]] as [$codigo, $valor]) {
        $item = $dom->createElement("item");
        $item->appendChild($dom->createElement("codigo", $codigo));
        $item->appendChild($dom->createElement("valor", number_format($valor, 2, '.', '')));
        $valores->appendChild($item);
    }
    $bilhete->appendChild($valores);

    $roteiro = $dom->createElement("roteiro");
    $locacao = $dom->createElement("locacao");
    $locacao->appendChild($dom->createElement("cidade_retirada", $cidadeRet));
    $locacao->appendChild($dom->createElement("local_retirada", $localRet));
    $locacao->appendChild($dom->createElement("dt_retirada", date('d/m/Y', strtotime($checkin))));
    $locacao->appendChild($dom->createElement("hr_retirada", $horaRet));
    $locacao->appendChild($dom->createElement("local_devolucao", $localDev));
    $locacao->appendChild($dom->createElement("dt_devolucao", date('d/m/Y', strtotime($checkout))));
    $locacao->appendChild($dom->createElement("hr_devolucao", $horaDev));
    $locacao->appendChild($dom->createElement("categ_veiculo", $categoria));
    $locacao->appendChild($dom->createElement("cod_tipo_pagto", $formaPgto));
    $locacao->appendChild($dom->createElement("dt_confirmacao", date('d/m/Y')));
    $locacao->appendChild($dom->createElement("confirmado_por", ""));
    $roteiro->appendChild($locacao);
    $bilhete->appendChild($roteiro);

    $bilhete->appendChild($dom->createElement("info_adicionais", $justificativa));


    $filename = 'xml/wintour-' . $requisicao . '.xml';
    $dom->save($filename);
    $arquivosGerados[] = $filename;

}

fclose($handle);
echo empty($arquivosGerados)
    ? json_encode(['error' => 'Nenhum arquivo foi gerado.'])
    : json_encode(['arquivos' => $arquivosGerados]);

ob_end_clean();
echo json_encode(['arquivos' => $arquivosGerados]);
exit;
