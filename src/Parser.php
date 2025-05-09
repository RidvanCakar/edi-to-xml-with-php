<?php

namespace Ridvan\EdiToXml;

use Monolog\Logger;

class Parser
{
    private Logger $logger;

    // Constructor accepts a Logger instance to log parsing events
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    // Reads and returns the EDI segments from a file
    public function readSegmentsFromFile(string $filePath): array
    {
        $this->logger->info("Reading EDI file: " . basename($filePath));

        if (!file_exists($filePath)) {
            $this->logger->error("File not found: $filePath");
            throw new \Exception("File not found: $filePath");
        }

        $content = file_get_contents($filePath);
        return explode("'", $content);
    }

    // Parses the EDI segments and returns an associative array with the parsed data
    public function parse(array $segments): array
    {
        $this->logger->info("Parsing EDI segments...");

        // Initialize the structure for parsed data
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
            'deliveryDateEarliest' => '',
            'deliveryDateLatest' => '',
            'freeTextField' => '',
            'promotionDealNumber' => '',
            'GLNBuyer' => '',
            'nameBuyer' => '',
            'addressBuyer' => '',
            'postalCodeBuyer' => '',
            'cityBuyer' => '',
            'countryCodeBuyer' => '',
            'GLNShipTo' => '',
            'nameShipTo' => '',
            'addressShipTo' => '',
            'postalCodeShipTo' => '',
            'cityShipTo' => '',
            'countryCodeShipTo' => '',
            'GLNInvoicee' => '',
            'nameInvoicee' => '',
            'addressInvoicee' => '',
            'postalCodeInvoicee' => '',
            'cityInvoicee' => '',
            'countryCodeInvoicee' => '',
            'GLNSupplier' => '',
            'supplierCode' => '',
            'nameSupplier' => '',
            'addressSupplier' => '',
            'postalCodeSupplier' => '',
            'citySupplier' => '',
            'countryCodeSupplier' => '',
            'currency' => '',
            'orderDetails' => [] 
        ];

        $currentDetail = null; // Temporary variable to hold item details

        // Loop through each segment and parse the relevant data
        foreach ($segments as $segment) {
            $segment = trim($segment);
            $fields = explode('+', $segment); // Split the segment by '+'
            $tag = $fields[0] ?? ''; // First element is the tag

            // Switch based on the tag to process different segments
            switch ($tag) {
                case 'UNB': // Interchange Header
                    $data['senderMailboxId'] = explode(':', $fields[2])[0] ?? '';
                    $data['receiverMailboxId'] = explode(':', $fields[3])[0] ?? '';
                    break;
                case 'NAD': // Name and Address
                    if (strpos($fields[1] ?? '', 'BY') !== false) {
                        $data['GLNBuyer'] = explode(':', $fields[2])[0] ?? '';
                    } elseif (strpos($fields[1] ?? '', 'DP') !== false) {
                        $data['GLNShipTo'] = explode(':', $fields[2])[0] ?? '';
                    } elseif (strpos($fields[1] ?? '', 'SU') !== false) {
                        $data['GLNSupplier'] = explode(':', $fields[2])[0] ?? '';
                    }
                    break;
                case 'BGM': // Beginning of Message
                    $data['orderNumber'] = $fields[2] ?? '';
                    break;
                case 'DTM': // Date/Time/Period
                    $code = explode(':', $fields[1])[0] ?? '';
                    $value = explode(':', $fields[1])[1] ?? '';
                    if ($code === '137') {
                        $data['orderDate'] = $value;
                        $data['documentDate'] = $value;
                    }
                    if ($code === '2') {
                        $data['deliveryDate'] = $value;
                    }
                    break;
                case 'FTX': // Free Text
                    $data['freeTextField'] = $fields[4] ?? '';
                    break;
                case 'CUX': // Currency
                    $data['currency'] = explode(':', $fields[1])[1] ?? '';
                    break;
                case 'LIN': // Line Item
                    if ($currentDetail) {
                        $data['orderDetails'][] = $currentDetail;
                    }
                    $currentDetail = [
                        'detailNumber' => $fields[1] ?? '',
                        'itemEanBarcode' => explode(':', $fields[3])[0] ?? '',
                        'ItemSenderCode' => '',
                        'itemReceiverCode' => '',
                        'itemDescription' => '',
                        'ItemGroupId' => '',
                        'ItemGrossWeight' => '',
                        'ItemNetWeight' => '',
                        'itemOrderedQuantity' => '',
                        'itemOrderedQuantityUom' => '',
                        'QuantityPerPack' => '',
                        'PackageType' => '',
                        'ItemGrossPrice' => '',
                        'itemNetPrice' => '',
                        'ItemDeliveryDate' => '',
                        'ItemBestBeforeDate' => '',
                    ];
                    break;
                case 'PIA': // Additional Product Id
                    if (strpos($fields[2] ?? '', 'SA') !== false) {
                        $currentDetail['itemReceiverCode'] = explode(':', $fields[2])[0] ?? '';
                    }
                    break;
                case 'IMD': // Item Description
                    $currentDetail['itemDescription'] = $fields[3] ?? '';
                    break;
                case 'QTY': // Quantity
                    $parts = explode(':', $fields[1]);
                    $currentDetail['itemOrderedQuantity'] = $parts[1] ?? '';
                    $currentDetail['itemOrderedQuantityUom'] = $parts[2] ?? '';
                    break;
                case 'PRI': // Price
                    $currentDetail['itemNetPrice'] = explode(':', $fields[1])[1] ?? '';
                    break;
            }
        }

        // Add the last detail to the orderDetails if available
        if ($currentDetail) {
            $data['orderDetails'][] = $currentDetail;
        }

        // Log the completion of the parsing process
        $this->logger->info("EDI parsing completed.");
        return $data; // Return the parsed data as an associative array
    }
}
