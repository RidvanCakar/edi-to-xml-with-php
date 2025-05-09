<?php

require 'vendor/autoload.php';

use Ridvan\EdiToXml\LoggerFactory;
use Ridvan\EdiToXml\Parser;
use Ridvan\EdiToXml\XmlGenerator;

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$inbox = $_ENV['INBOX_DIR'];
$outbox = $_ENV['OUTBOX_DIR'];
$archive = $_ENV['ARCHIVE_DIR'];
$error = $_ENV['ERROR_DIR'];
$logFile = $_ENV['LOG_FILE'];

// Initialize logger
$log = LoggerFactory::create('ftp_edifact', $logFile);
$log->info("Process started.");

// Get all EDI files
$ediFiles = array_merge(
    glob($inbox . '/*.EDI'),
    glob($inbox . '/*.edi')
);

// No files? Log and exit
if (empty($ediFiles)) {
    $log->error("No EDI files found.");
    exit;
}

// Initialize Parser and XmlGenerator once
$parser = new Parser($log);
$xmlGenerator = new XmlGenerator($log);

// Loop through all EDI files
foreach ($ediFiles as $ediFile) {
    $ediFileName = basename($ediFile);
    $log->info("Processing EDI file: $ediFileName");

    try {
        // Read and parse the segments
        $segments = $parser->readSegmentsFromFile($ediFile);
        $ediData = $parser->parse($segments);
        $xmlOutput = $xmlGenerator->generate($ediData);

        // Output path: include timestamp + uniqid to avoid collision
        $xmlPath = $outbox . '/outXML_' . time() . '_' . uniqid() . '.xml';
        file_put_contents($xmlPath, $xmlOutput);
        $log->info("XML file created: $xmlPath");

        // Move to archive
        if (rename($ediFile, $archive . '/' . $ediFileName)) {
            $log->info("Archived EDI file: $ediFileName");
        } else {
            $log->error("Failed to archive EDI file: $ediFileName");
        }
    } catch (\Throwable $e) {
        $log->error("Error processing $ediFileName: " . $e->getMessage());
        // Optionally move to error folder
        rename($ediFile, $error . '/' . $ediFileName);
    }
}

$log->info("All files processed.");
