<?php declare(strict_types=1);
/**
 * File: Address.php
 * Category: -
 * Author: M.Goldenbaum
 * Created: 17.09.20 20:38
 * Updated: -
 *
 * Description:
 *  -
 */

namespace Yai\Ymap\Connection\ConnectionLibrary;

/**
 * Class Address
 *
 * @package Yai\Ymap\Connection\ConnectionLibrary
 */
class Address {

    /**
     * @var string $personal
     */
    public string $personal = "";

    /**
     * @var string $mailbox
     */
    public string $mailbox = "";

    /**
     * @var string $host
     */
    public string $host = "";

    /**
     * @var string $mail
     */
    public string $mail = "";

    /**
     * @var string $full
     */
    public string $full = "";

    /**
     * Address constructor.
     * @param object $address
     */
    public function __construct(object $address) {
        $this->personal = $address->personal ?? "";
        $this->mailbox = $address->mailbox ?? "";
        $this->host = $address->host ?? "";
        $this->mail = ($this->mailbox && $this->host) ? $this->mailbox . "@" . $this->host : "";
        $this->full = ($this->personal ? $this->personal . " <" . $this->mail . ">" : $this->mail);
    }

    /**
     * Return the string representation of the object
     *
     * @return string
     */
    public function __toString(): string {
        return $this->full;
    }
}
