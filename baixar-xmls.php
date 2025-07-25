<?php
define('XML_DIR', __DIR__ . '/xml');

$zipFile = tempnam(sys_get_temp_dir(), 'xmls') . '.zip';
$zip = new ZipArchive;

if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
    $files = glob(XML_DIR . '/*.xml');
    foreach ($files as $file) {
        $zip->addFile($file, basename($file));
    }
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=xmls_' . date('Ymd_His') . '.zip');
    header('Content-Length: ' . filesize($zipFile));
    readfile($zipFile);
    unlink($zipFile);
} else {
    echo "Erro ao criar o arquivo ZIP.";
}
