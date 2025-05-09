<?php

namespace Ridvan\EdiToXml;

use SimpleXMLElement;
use Monolog\Logger;

class XmlGenerator
{
    private Logger $logger;

    // Constructor accepts a Logger instance to log the XML generation process
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    // Generates the XML from the provided data array
    public function generate(array $data): string
    {
        $this->logger->info("Generating XML...");

        // Create the root XML element
        $xml = new SimpleXMLElement('<OrderRequest/>');

        // Add the OrderHeader node with all its child elements
        $header = $xml->addChild('OrderHeader');
        foreach ([
            'Snrf', 'DocumentDate', 'DocumentTime', 'SenderMailboxId', 'ReceiverMailboxId',
            'OrderType', 'OrderNumber', 'OrderDate', 'DeliveryDate', 'DeliveryDateEarliest',
            'DeliveryDateLatest', 'FreeTextField', 'PromotionDealNumber', 'GLNBuyer', 'NameBuyer',
            'AddressBuyer', 'PostalCodeBuyer', 'CityBuyer', 'CountryCodeBuyer', 'GLNShipTo',
            'NameShipTo', 'AddressShipTo', 'PostalCodeShipTo', 'CityShipTo', 'CountryCodeShipTo',
            'GLNInvoicee', 'NameInvoicee', 'AddressInvoicee', 'PostalCodeInvoicee', 'CityInvoicee',
            'CountryCodeInvoicee', 'GLNSupplier', 'SupplierCode', 'NameSupplier', 'AddressSupplier',
            'PostalCodeSupplier', 'CitySupplier', 'CountryCodeSupplier', 'Currency'
        ] as $field) {
            // Add each field to the OrderHeader node
            $this->addXmlChild($header, $field, $data[lcfirst($field)] ?? '');
        }

        // Adding GLNBuyer, GLNShipTo, GLNSupplier explicitly as they are used multiple times
        $this->addXmlChild($header, 'GLNBuyer', $data['GLNBuyer'] ?? '');
        $this->addXmlChild($header, 'GLNShipTo', $data['GLNShipTo'] ?? '');
        $this->addXmlChild($header, 'GLNSupplier', $data['GLNSupplier'] ?? '');

        // Add the OrderDetails node
        $details = $xml->addChild('OrderDetails');
        foreach ($data['orderDetails'] as $detail) {
            // Add each item as a Detail node under OrderDetails
            $node = $details->addChild('Detail');
            foreach ([
                'DetailNumber' => 'detailNumber',
                'ItemEanBarcode' => 'itemEanBarcode',
                'ItemSenderCode' => 'itemSenderCode',
                'ItemReceiverCode' => 'itemReceiverCode',
                'ItemDescription' => 'itemDescription',
                'ItemGroupId' => 'itemGroupId',
                'ItemGrossWeight' => 'itemGrossWeight',
                'ItemNetWeight' => 'itemNetWeight',
                'ItemOrderedQuantity' => 'itemOrderedQuantity',
                'ItemOrderedQuantityUom' => 'itemOrderedQuantityUom',
                'QuantityPerPack' => 'quantityPerPack',
                'PackageType' => 'packageType',
                'ItemGrossPrice' => 'itemGrossPrice',
                'ItemNetPrice' => 'itemNetPrice',
                'ItemDeliveryDate' => 'itemDeliveryDate',
                'ItemBestBeforeDate' => 'itemBestBeforeDate',
            ] as $tag => $key) {
                // Add each child element to the Detail node
                $this->addXmlChild($node, $tag, $detail[$key] ?? '');
            }
        }

        // Log the completion of the XML generation process
        $this->logger->info("XML generation completed.");
        
        // Return the XML as a string
        return $xml->asXML();
    }

    // Helper function to add a child element to the parent node
    private function addXmlChild($parent, $name, $value)
    {
        // If value is empty, add an empty string. Otherwise, escape the value and add it.
        $parent->addChild($name, $value === '' ? '' : htmlspecialchars($value));
    }
}
