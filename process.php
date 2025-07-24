<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuração banco
$host = 'localhost';
$db = 'importviagens';
$user = 'root';
$pass = '1234'; // <<< ajuste conforme seu ambiente

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
} catch (PDOException $e) {
    die("Erro DB: " . $e->getMessage());
}

function formatarData($data) {
    if (!$data) return '';
    $parts = explode("/", $data);
    if (count($parts) !== 3) return '';
    return $parts[2] . '-' . str_pad($parts[1], 2, "0", STR_PAD_LEFT) . '-' . str_pad($parts[0], 2, "0", STR_PAD_LEFT);
}

function limparValorDecimal($valor) {
    $limpo = str_replace(['R$', ' ', '.', "'"], '', $valor);
    $limpo = str_replace(',', '.', $limpo);
    if (trim($limpo) === '' || trim($limpo) === '-' || trim($limpo) === 'R$') {
        return 0;
    }
    return floatval($limpo);
}

// Upload
if ($_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
    die("Erro ao enviar arquivo.");
}

$csvFile = $_FILES['csvfile']['tmp_name'];
$handle = fopen($csvFile, 'r');
if (!$handle) die("Erro ao abrir arquivo.");

// Cabeçalhos
$headers = fgetcsv($handle, 0, ",");

if (!in_array("Handle", $headers)) {
    echo "<h3>Colunas encontradas:</h3><pre>";
    print_r($headers);
    echo "</pre>";
    die("O arquivo CSV não tem o cabeçalho esperado.");
}

$arquivosGerados = [];

while (($row = fgetcsv($handle, 0, ",")) !== false) {
    if (empty($row[0])) continue;

    $registro = array_combine($headers, $row);

    $handleId = strtolower($registro['Handle']);
    $dataEmissao = formatarData($registro['DataEmissão']);
    $bilhete = strtolower($registro['Bilhete']);
    $ciaOrig = strtoupper(trim($registro['CiaAérea']));
    $pagamentoOrig = strtoupper(trim($registro['FormaPagamento']));
    $emissor = strtolower($registro['Emissor']);
    $cliente = strtolower($registro['InformaçãoCliente']);
    $aprovador = strtolower($registro['AprovadorEfetivo']);
    $requisicao = strtolower($registro['RequisiçãoBenner']);
    $localizador = strtolower($registro['AéreoLocalizador']);
    $passageiro = strtolower($registro['PassageiroNomeCompleto']);
    $centroDescritivo = strtolower($registro['CentroDescritivo']);
    $tarifa = limparValorDecimal($registro['TarifaEmitida']);
    $taxas = limparValorDecimal($registro['Taxas']);
    $taxa_du = limparValorDecimal($registro['DescontoAéreo']);
    $classe = strtolower($registro['ClasseVoo']);
    $origem = strtolower($registro['AeroportoOrigem']);
    $destino = strtolower($registro['AeroportoDestino']);
    $dataEmbarque = formatarData($registro['DataEmbarque']);

    // Mapeia prestador_svc
    $prestador = match ($ciaOrig) {
        "LATAM" => "la",
        "AZUL" => "ad",
        "GOL" => "g3",
        default => strtolower($ciaOrig)
    };

    // Mapeia forma_de_pagamento
    $formaPagamento = match ($pagamentoOrig) {
        "CARTAO" => "cc",
        "FATURADO" => "iv",
        default => strtolower($pagamentoOrig)
    };

    // Insere no banco
    $stmt = $pdo->prepare("INSERT INTO bilhetes_aereos (
        handle, data_emissao, bilhete, cia_aerea, forma_pagamento, emissor,
        informacao_cliente, aprovador_efetivo, requisicao_benner, localizador,
        passageiro_nome_completo,centro_descritivo,tarifa_emitida, taxas, desconto_aereo,
        classe_voo, aeroporto_origem, aeroporto_destino, data_embarque
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->execute([
        $handleId, $dataEmissao, $bilhete, strtolower($ciaOrig), $formaPagamento, $emissor,
        $cliente, $aprovador, $requisicao, $localizador,
        $passageiro, $centroDescritivo, $tarifa,  $taxas, $taxa_du,
        $classe, $origem, $destino, $dataEmbarque
    ]);

    // Cria XML com DOMDocument (para indentação bonita)
    $dom = new DOMDocument("1.0", "iso-8859-1");
    $dom->formatOutput = true;

    $root = $dom->createElement("bilhetes");
    $dom->appendChild($root);

    $nrArquivo = $dom->createElement("nr_arquivo", $requisicao);
    $root->appendChild($nrArquivo);
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
    $bilheteEl->appendChild($dom->createElement("forma_de_pagamento", $formaPagamento));
    $bilheteEl->appendChild($dom->createElement("moeda", "brl"));
    $bilheteEl->appendChild($dom->createElement("emissor", $emissor));
    $bilheteEl->appendChild($dom->createElement("cliente", $cliente));
    $bilheteEl->appendChild($dom->createElement("ccustos_cliente", $centroDescritivo));
    $bilheteEl->appendChild($dom->createElement("aprovador", $aprovador));
    $bilheteEl->appendChild($dom->createElement("numero_requisicao", $requisicao));
    $bilheteEl->appendChild($dom->createElement("localizador", $localizador));
    $bilheteEl->appendChild($dom->createElement("passageiro", $passageiro));
    $bilheteEl->appendChild($dom->createElement("tipo_domest_inter", "d"));
    $bilheteEl->appendChild($dom->createElement("tipo_roteiro", "1"));

    $valores = $dom->createElement("valores");
    $bilheteEl->appendChild($valores);

    foreach ([
        ["tarifa", $tarifa],
        ["taxa", $taxas],
        ["taxa_du", $taxa_du]
    ] as [$codigo, $valor]) {
        $item = $dom->createElement("item");
        $item->appendChild($dom->createElement("codigo", $codigo));
        $item->appendChild($dom->createElement("valor", number_format($valor, 2, '.', '')));
        $valores->appendChild($item);
    }

    $roteiro = $dom->createElement("roteiro");
    $aereo = $dom->createElement("aereo");
    $roteiro->appendChild($aereo);
    $bilheteEl->appendChild($roteiro);

    // Trecho ida
    foreach ([["origem" => $origem, "destino" => $destino, "hora" => "08:00", "horaChegada" => "10:00"],
              ["origem" => $destino, "destino" => $origem, "hora" => "19:00", "horaChegada" => "21:00"]] as $t) {
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

    $filename = 'xml/wintour-' . $requisicao . '.xml';
    $dom->save($filename);
    $arquivosGerados[] = $filename;
}

fclose($handle);

// Links
echo "<h3>Arquivos gerados:</h3>";
foreach ($arquivosGerados as $arq) {
    echo "<a href='$arq' download>" . basename($arq) . "</a><br>";
}
?>
