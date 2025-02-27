<?php

namespace charlieuki\ReceiptPrinter;

class Store
{
    private $mid = '';
    private $name = '';
    private $address = '';
    private $email = '';
    private $website = '';

    function __construct($mid, $name, $address, $email, $website) {
        $this->mid = $mid;
        $this->name = $name;
        $this->address = $address;
        $this->email = $email;
        $this->website = $website;
    }

    public function getMID() {
        return $this->mid;
    }

    public function getName() {
        return $this->name;
    }

    public function getAddress() {
        return $this->address;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getWebsite() {
        return $this->website;
    }
}