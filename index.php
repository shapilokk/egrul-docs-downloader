<?php

require 'vendor/autoload.php';

use Egrul\EgrulDocumentDownloader;

try {
    $documentDownloader = new EgrulDocumentDownloader('1037700258694');
    if($documentDownloader->ready()) {
        $document = $documentDownloader->getDocument();
        file_put_contents('doc.pdf', $document);
    }
} catch (Exception $e) {
    exit($e);
}