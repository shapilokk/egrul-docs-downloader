<?php

require 'vendor/autoload.php';

use Egrul\DocumentDownloader;

try {
    $documentDownloader = new DocumentDownloader('1037700258694333');
    if($documentDownloader->ready()) {
        $document = $documentDownloader->getDocument();
        file_put_contents('doc.pdf', $document);
    }
} catch (Exception $e) {
    echo ($e->getMessage());
}