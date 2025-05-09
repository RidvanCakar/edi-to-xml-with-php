<?php

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get environment variables
$inbox = $_ENV['INBOX_DIR'];
$outbox = $_ENV['OUTBOX_DIR'];
$archive = $_ENV['ARCHIVE_DIR'];
$logPath = $_ENV['LOG_FILE'];

// Initialize logger
$log = new Logger('edifact');
$log->pushHandler(new StreamHandler($logPath, Logger::DEBUG));
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG)); // also log to terminal
$log->info("Process started.");

// Check for EDI files in inbox folder
$ediFiles = array_merge(
    glob($inbox . '/*.EDI'),
    glob($inbox . '/*.edi')
);
if(!$ediFiles){
    $log->error("No EDI files found in inbox folder.");
    exit;
}

$ediFile = $ediFiles[0];
$ediFileName = basename($ediFile);
$log->info("EDI file found: $ediFileName");

// Read the EDI file and split into segments
$segments = explode("'", file_get_contents($ediFile));

// Process segments
$ediData = ediParser($segments, $log);

// Generate XML
$xmlOutput = createOrderXML($ediData);

// Save XML file to outbox folder
$xmlPath = $outbox . '/outXML_' . time() . '.xml';
file_put_contents($xmlPath, $xmlOutput);
$log->info("XML file created: $xmlPath");

// Move EDI file to archive
if (rename($ediFile, to: $archive . '/' . $ediFileName)) {
    $log->info("EDI file archived.");
} else {
    $log->error("Failed to archive EDI file.");
}


// Parses EDI segments and returns structured data

function ediParser(array $segments,$log)
{
    $log->info("Parsing EDI file...");
    global $log;

    // Initialize data structure
    $data = [
        'snrf' => '173831239',
        'documentDate' => '',
        'documentTime' => '1625',
        'senderMailboxId' => '',
        'receiverMailboxId' => '',
        'orderType' => 'Original order',
        'orderNumber' => '',
        'orderDate' => '',
        'deliveryDate' => '',
        'freeTextField' => '',
        'currency' => '',
        'GLNBuyer' => '',
        'GLNShipTo' => '',
        'GLNSupplier' => '',
        'orderDetails' => []
    ];

    // Temporary storage for current detail line
    $currentDetail = null;

    foreach ($segments as $segment) {
        $segment = trim($segment); // Remove whitespace
        $fields = explode('+', $segment); // Split segment by '+'
        $tag = $fields[0] ?? '';

        // Uncomment to log every processed segment
        //$log->info("Processing segment: $segment");

        switch ($tag) {
            case 'UNB': // Sender/receiver IDs and date/time
                $data['senderMailboxId'] = explode(':', $fields[2])[0] ?? '';
                $data['receiverMailboxId'] = explode(':', $fields[3])[0] ?? '';
                break;

            case 'NAD': // GLN info: Buyer, ShipTo, Supplier
                if (strpos($fields[1] ?? '', 'BY') !== false) {
                    $data['GLNBuyer'] = explode(':', $fields[2])[0] ?? '';
                } elseif (strpos($fields[1] ?? '', 'DP') !== false) {
                    $data['GLNShipTo'] = explode(':', $fields[2])[0] ?? '';
                } elseif (strpos($fields[1] ?? '', 'SU') !== false) {
                    $data['GLNSupplier'] = explode(':', $fields[2])[0] ?? '';
                }
                break;

            case 'BGM': // Order number
                $data['orderNumber'] = $fields[2] ?? '';
                break;

            case 'DTM': // Order and delivery dates
                $code = explode(':', $fields[1])[0] ?? '';
                $value = explode(':', $fields[1])[1] ?? '';
                if ($code === '137') $data['orderDate'] = $value;
                if ($code === '137') $data['documentDate'] = $value;
                if ($code === '2') $data['deliveryDate'] = $value;
                break;

            case 'FTX': // Free text field
                $data['freeTextField'] = $fields[4] ?? '';
                break;

            case 'CUX': // Currency
                $data['currency'] = explode(':', $fields[1])[1] ?? '';
                break;

            case 'LIN': // Start a new product detail line
                if ($currentDetail) $data['orderDetails'][] = $currentDetail;
                $currentDetail = [
                    'detailNumber' => $fields[1] ?? '',
                    'itemEanBarcode' => explode(':', $fields[3])[0] ?? '',
                    'itemReceiverCode' => '',
                    'itemDescription' => '',
                    'itemOrderedQuantity' => '',
                    'itemOrderedQuantityUom' => '',
                    'itemNetPrice' => ''
                ];
                break;

            case 'PIA': // Receiver code
                if (strpos($fields[2] ?? '', 'SA') !== false) {
                    $currentDetail['itemReceiverCode'] = explode(':', $fields[2])[0] ?? '';
                }
                break;

            case 'IMD': // Product description
                $currentDetail['itemDescription'] = $fields[3] ?? '';
                break;

            case 'QTY': // Ordered quantity and unit of measure
                $parts = explode(':', $fields[1]);
                $currentDetail['itemOrderedQuantity'] = $parts[1] ?? '';
                $currentDetail['itemOrderedQuantityUom'] = $parts[2] ?? '';
                break;

            case 'PRI': // Unit price
                $currentDetail['itemNetPrice'] = explode(':', $fields[1])[1] ?? '';
                break;
        }
    }

    // Add the last detail line if exists
    if ($currentDetail) {
        $data['orderDetails'][] = $currentDetail;
    }

    return $data;
}

// Helper to add XML element even if value is empty
function addXmlChild($parent, $name, $value) {
    $parent->addChild($name, $value === '' ? '' : htmlspecialchars($value));
}

// Generate XML from parsed EDI data
function createOrderXML($data) {
    $xml = new SimpleXMLElement('<OrderRequest/>');

    // Header fields
    $header = $xml->addChild('OrderHeader');
    foreach ([
        'Snrf', 'DocumentDate', 'DocumentTime', 'SenderMailboxId', 'ReceiverMailboxId',
        'OrderType', 'OrderNumber', 'OrderDate', 'DeliveryDate',
        'FreeTextField', 'Currency'
    ] as $field) {
        addXmlChild($header, $field, $data[lcfirst($field)] ?? '');
    }

    // GLN fields added manually
    addXmlChild($header, 'GLNBuyer', $data['GLNBuyer'] ?? '');
    addXmlChild($header, 'GLNShipTo', $data['GLNShipTo'] ?? '');
    addXmlChild($header, 'GLNSupplier', $data['GLNSupplier'] ?? '');

    // Detail lines
    $details = $xml->addChild('OrderDetails');
    foreach ($data['orderDetails'] as $detail) {
        $node = $details->addChild('Detail');
        foreach ([
            'DetailNumber' => 'detailNumber',
            'ItemEanBarcode' => 'itemEanBarcode',
            'ItemReceiverCode' => 'itemReceiverCode',
            'ItemDescription' => 'itemDescription',
            'ItemOrderedQuantity' => 'itemOrderedQuantity',
            'ItemOrderedQuantityUom' => 'itemOrderedQuantityUom',
            'ItemNetPrice' => 'itemNetPrice'
        ] as $tag => $key) {
            addXmlChild($node, $tag, $detail[$key] ?? '');
        }
    }

    return $xml->asXML();
}
