<?php

require 'vendor/autoload.php';

use Ridvan\EdiToXml\LoggerFactory;
use Ridvan\EdiToXml\Parser;
use Ridvan\EdiToXml\XmlGenerator;

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Paths for input, output, and archive directories
$inbox = $_ENV['INBOX_DIR'];
$outbox = $_ENV['OUTBOX_DIR'];
$archive = $_ENV['ARCHIVE_DIR'];
$logFile = $_ENV['LOG_FILE'];

// Initialize logger with a specified file path for logs
$log = LoggerFactory::create('ftp_edifact', $logFile);
$log->info("Process started.");

// Get list of EDI files from the inbox directory
$ediFiles = array_merge(
    glob($inbox . '/*.EDI'),
    glob($inbox . '/*.edi')
);

// Check if there are any EDI files, if not log an error and exit
if (empty($ediFiles)) {
    $log->error("No EDI files found.");
    exit;
}

// Select the first EDI file found
$ediFile = $ediFiles[0];
$ediFileName = basename($ediFile);
$log->info("EDI file found: $ediFileName");

// Read the segments from the EDI file
$segments = explode("'", file_get_contents($ediFile));

// Parse the EDI segments using the Parser class
$parser = new Parser($log);
$ediData = $parser->parse($segments);

// Generate the XML output using the XmlGenerator class
$xmlGenerator = new XmlGenerator($log);
$xmlOutput = $xmlGenerator->generate($ediData);

// Save the generated XML to the output directory with a timestamped filename
$xmlPath = $outbox . '/outXML_' . time() . '.xml';
file_put_contents($xmlPath, $xmlOutput);
$log->info("XML file created: $xmlPath");

// Attempt to move the processed EDI file to the archive directory
if (rename($ediFile, $archive . '/' . $ediFileName)) {
    $log->info("EDI file archived.");
} else {
    $log->error("Failed to archive EDI file.");
}
