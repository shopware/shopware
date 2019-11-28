<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Struct;

class Currency
{
    /**
     * @var string
     */
    private $currency;

    /**
     * @param string $Currency
     */
    private function __construct($Currency)
    {
        $this->currency = $Currency;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->currency;
    }

    /**
     * Returns a list of valid Currencies
     *
     * @return array
     */
    public static function getValidCurrencies()
    {
        return [
            'EUR',
            'USD',
            'GBP',
        ];
    }

    /**
     * @param string $Currency
     *
     * @return Currency
     */
    public static function createFromString($Currency)
    {
        return new self($Currency);
    }
}
