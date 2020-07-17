<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Struct;

class Shop extends Struct
{
    /**
     * @var string
     */
    public $salesChannelId;

    /**
     * Name of the shop e.g. "My example shop"
     *
     * @var string
     */
    public $name;

    /**
     * Shop owner email address
     *
     * @var string
     */
    public $email;

    /**
     * Shop host including port
     * e.g.
     * "localhost:8080"
     * "my-example.com"
     *
     * @var string
     */
    public $host;

    /**
     * Base path to shop if installed in a sub directory
     * Leave blank if installed in root dir
     *
     * @var string
     */
    public $basePath;

    /**
     * Default shop locale e.g. "en-GB"
     *
     * @var string
     */
    public $locale;

    /**
     * Default shopware currency e.g. "EUR"
     *
     * @var string
     */
    public $currency = 'EUR';

    /**
     * Default shop country e.g. "Sweden"
     *
     * @var string
     */
    public $country;

    /**
     * Additional currencies that will get installed
     *
     * @var array|null
     */
    public $additionalCurrencies = null;
}
