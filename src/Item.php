<?php

namespace charlieuki\ReceiptPrinter;

class Item
{
    private $paper_size;
    private $name;
    private $qty;
    private $price;
    private $discount;
    private $currency = 'Rp';

    function __construct($name, $qty, $price, $discount) {
        $this->name = $name;
        $this->qty = $qty;
        $this->price = $price;
        $this->discount = $discount;
        $this->paper_size = '57mm';
    }

    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    public function setPaperSize($paper_size) {
        $this->paper_size = $paper_size;
    }

    public function getQty() {
        return $this->qty;
    }

    public function getPrice() {
        return $this->price;
    }

    public function getDiscount() {
        return $this->discount;
    }

    public function formatForReceipt($itemNameColumnWidth)
    {
        $item_subtotal = number_format(($this->price - $this->discount) * $this->qty, 0, ',', '.') .' '.$this->currency;

        $lines = explode("\n", wordwrap("{$this->qty}  {$this->name}", $itemNameColumnWidth));
        $firstLine = array_shift($lines);
        $formattedLines = [];

        $firstLine = str_pad($firstLine, $itemNameColumnWidth);
        $formattedLines[] = "$firstLine $item_subtotal";

        foreach ($lines as $line) {
            $line = str_pad('   '.$line, $itemNameColumnWidth);
            $formattedLines[] = $line;
        }

        return implode("\n", $formattedLines);
    }
}