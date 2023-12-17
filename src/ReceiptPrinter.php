<?php

namespace charlieuki\ReceiptPrinter;

use charlieuki\ReceiptPrinter\Item as Item;
use charlieuki\ReceiptPrinter\Store as Store;
use Mike42\Escpos\Printer;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\CupsPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

class ReceiptPrinter
{
    private $printer;
    private $logo;
    private $store;
    private $items;
    private $phones;
    private $date;
    private $currency = 'da';
    private $subtotal = 0;
    private $total = 0;
    private $discount = 0;
    private $tax_percentage = 10;
    private $tax = 0;
    private $request_amount = 0;
    private $qr_code = null;

    function __construct() {
        $this->printer = null;
        $this->items = [];
    }

    public function init($connector_type, $connector_descriptor, $connector_port = 9100) {
        switch (strtolower($connector_type)) {
            case 'cups':
                $connector = new CupsPrintConnector($connector_descriptor);
                break;
            case 'windows':
                $connector = new WindowsPrintConnector($connector_descriptor);
                break;
            case 'network':
                $connector = new NetworkPrintConnector($connector_descriptor);
                break;
            default:
                $connector = new FilePrintConnector("php://stdout");
                break;
        }

        if ($connector) {
            // Load simple printer profile
            $profile = CapabilityProfile::load("default");
            // Connect to printer
            $this->printer = new Printer($connector, $profile);
        } else {
            throw new Exception('Invalid printer connector type. Accepted values are: cups');
        }
    }

    public function close() {
        if ($this->printer) {
            $this->printer->close();
        }
    }

    public function setStore($mid, $name, $address, $email, $website) {
        $this->store = new Store($mid, $name, $address, $email, $website);
    }

    public function setLogo($logo) {
        $this->logo = $logo;
    }

    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    public function setDate($date) {
        $this->date = $date;
    }

    public function addPhones($phones) {
        $this->phones = $phones;
    }

    public function addTotal($total) {
        $this->total = $total;
    }

    public function addSubTotal($subtotal) {
        $this->subtotal = $subtotal;
    }

    public function addItem($name, $qty, $price, $discount) {
        $item = new Item($name, $qty, $price, $discount);
        $item->setCurrency($this->currency);
        
        $this->items[] = $item;
    }

    public function setRequestAmount($amount) {
        $this->request_amount = $amount;
    }

    public function setTax($tax) {
        $this->tax_percentage = $tax;

        $this->tax = (int) $this->tax_percentage / 100 * (int) $this->subtotal;
    }
    public function calculateDiscount() {
        $this->discount = 0;

        foreach ($this->items as $item) {
            $this->discount += (int) $item->getQty() * $item->getDiscount();
        }
    }

    public function setQRcode($content) {
        $this->qr_code = $content;
    }

    public function setTextSize($width = 1, $height = 1) {
        if ($this->printer) {
            $width = ($width >= 1 && $width <= 8) ? (int) $width : 1;
            $height = ($height >= 1 && $height <= 8) ? (int) $height : 1;
            $this->printer->setTextSize($width, $height);
        }
    }

    public function getPrintableQRcode() {
        return $this->qr_code;
    }

    public function getPrintableSummary($label, $value, $is_double_width = false) {
        $left_cols = $is_double_width ? 6 : 12;
        $right_cols = $is_double_width ? 10 : 20;

        $formatted_value = number_format($value, 0, ',', '.') .' '.$this->currency;

        return str_pad($label, $left_cols) . str_pad($formatted_value, $right_cols, ' ', STR_PAD_LEFT);
    }

    public function feed($feed = NULL) {
        $this->printer->feed($feed);
    }

    public function cut() {
        $this->printer->cut();
    }

    public function printDashedLine() {
        $line = '';

        for ($i = 0; $i < 32; $i++) {
            $line .= '-';
        }

        $this->printer->text($line);
    }

    public function printLogo($mode = 0) {
        if ($this->logo) {
            $this->printImage($this->logo, $mode);
        }
    }

    public function printImage($image_path, $mode = 0) {
        if ($this->printer && $image_path) {
            $image = EscposImage::load($image_path);

            $this->printer->feed();

            switch ($mode) {
                case 0:
                    $this->printer->graphics($image);
                    break;
                case 1:
                    $this->printer->bitImage($image);
                    break;
                case 2:
                    $this->printer->bitImageColumnFormat($image);
                    break;
            }

            $this->printer->feed();
        }
    }

    public function printQRcode() {
        if ($this->qr_code) {
            $this->printer->qrCode($this->getPrintableQRcode(), Printer::QR_ECLEVEL_L, 8);
        }
    }

    public function openDrawer($pin = 0, $on_duration = 120, $off_duration = 240) {
        if ($this->printer) {
            $this->printer->pulse($pin, $on_duration, $off_duration);
        }
    }

    public function printReceipt($with_items = true) {
        if ($this->printer) {
            $subtotal = $this->getPrintableSummary('Sous-Total:', $this->subtotal);
            $discount = $this->getPrintableSummary('Remise:', $this->discount);
            $tax = $this->getPrintableSummary('Tax:', $this->tax);
            $total = $this->getPrintableSummary('TOTAL:', $this->total, true);
            $header = str_pad('N°: ' . $this->store->getMID(), 16);
            $footer = "Merci pour votre visite\n";
            // Init printer settings
            $this->printer->initialize();
            $this->printer->selectPrintMode();
            // Set margins
            $this->printer->setPrintLeftMargin(1);
            // Print receipt headers
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            // Image print mode
            $image_print_mode = 0; // 0 = auto; 1 = mode 1; 2 = mode 2
            // Print logo
            $this->printLogo($image_print_mode);
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->feed(2);
            $this->printer->text("{$this->store->getName()}\n");
            $this->printer->selectPrintMode();
            $this->printer->text("{$this->store->getAddress()}\n");
            foreach ($this->phones as $phone){
                $this->printer->text("{$phone}\n");
            }   
            $this->printer->text($header . "\n");
            $this->printer->feed();
            // Print receipt title
            $this->printer->setEmphasis(true);
            $this->printer->text("REÇU\n");
            $this->printer->setEmphasis(false);
            $this->printer->feed();
            // Print items
            if ($with_items) {
                $this->printer->setJustification(Printer::JUSTIFY_LEFT);
                foreach ($this->items as $item) {
                    $formattedItem = $item->formatForReceipt(35);
                    $this->printer->text("$formattedItem\n");
                }
                $this->printer->feed();
            }
            $this->printer->setEmphasis(true);
            $this->printer->text($subtotal);
            $this->printer->setEmphasis(false);
            $this->printer->feed();
            $this->printer->text($discount);
            if($this->tax){
                $this->printer->feed();
                $this->printer->text($tax);
            }   
            $this->printer->feed(2);
            // Print grand total
            $this->printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printer->text($total);
            $this->printer->feed();
            $this->printer->selectPrintMode();
            // Print qr code
            $this->printQRcode();
            // Print receipt footer
            $this->printer->feed();
            $this->printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printer->text($footer);
            $this->printer->feed();
            // Print receipt date
            $this->printer->text($this->date);
            $this->printer->feed(2);
            // Cut the receipt
            $this->printer->cut();
            // Open drawer
            $this->openDrawer();
            $this->printer->close();
        } else {
            throw new Exception('Printer has not been initialized.');
        }
    }
    public function printReceiptV($with_items = true) {
        if ($this->printer) {
            if ($with_items) {
                $this->printer->setJustification(Printer::JUSTIFY_LEFT);
                foreach ($this->items as $item) {
                    $formattedItem = $item->formatForReceipt(35);
                    $this->printer->text("$formattedItem\n");
                }
                $this->printer->feed();
            }
            $this->printer->cut();
            $this->printer->close();
        } else {
            throw new Exception('Printer has not been initialized.');
        }
    }
}