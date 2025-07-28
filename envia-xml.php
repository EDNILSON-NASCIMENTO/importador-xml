<?php
define('XML_DIR', __DIR__ . '/xml');
define('LOG_DIR', __DIR__ . '/logs/wintour');

function savelog($fileName, $message) {
    if (!is_dir(dirname($fileName))) {
        mkdir(dirname($fileName), 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($fileName, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

function sendXmlToWintour($xmlContent, $fileName) {
    $soap = <<<XML
<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                  xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:urn="urn:HubInterfacesIntf-IHubInterfaces">
  <soapenv:Header/>
  <soapenv:Body>
    <urn:importaArquivo2 soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
      <aPin xsi:type="xsd:string">yP1V82E2aKf8GNRfx3abgEiCjg==</aPin>
      <aArquivo xsi:type="xsd:string">{XML}</aArquivo>
      <aLivre xsi:type="xsd:string">UNIGLOBEPRO</aLivre>
    </urn:importaArquivo2>
  </soapenv:Body>
</soapenv:Envelope>
XML;

    $envelope = str_replace('{XML}', base64_encode($xmlContent), $soap);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.digirotas.com/HubInterfacesSoap/soap/IHubInterfaces');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $envelope);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml;charset=UTF-8']);
    curl_setopt($ch, CURLOPT_USERPWD, 'hubstur:Patrick@102030');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    $logFile = LOG_DIR . '/envio_' . date('Ymd') . '.log';
    savelog($logFile, "ARQUIVO: $fileName");
    savelog($logFile, "SOAP: $envelope");
    savelog($logFile, "RESPOSTA: " . ($response ?: $error));

    // ✅ Atualiza status no formato compatível com strtotime()
    $statusFile = __DIR__ . '/logs/envios_status.json';
    $status = file_exists($statusFile) ? json_decode(file_get_contents($statusFile), true) : [];
    $status[$fileName] = date('Y-m-d H:i:s');
    file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));

    echo $response ? "✅ Enviado com sucesso" : "❌ Erro no envio: $error";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['xml_files'])) {
    $file = basename($_POST['xml_files']);
    $path = XML_DIR . '/' . $file;
    if (!file_exists($path)) {
        echo "❌ Arquivo não encontrado: $file";
        exit;
    }

    $xml = file_get_contents($path);
    sendXmlToWintour($xml, $file);
}
