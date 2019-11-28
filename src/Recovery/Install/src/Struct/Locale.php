<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Struct;

class Locale
{
    /**
     * @var string
     */
    private $locale;

    /**
     * @param string $locale
     */
    private function __construct($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->locale;
    }

    /**
     * Returns a list of valid locales
     *
     * @return array
     */
    public static function getValidLocales()
    {
        return [
            'de-DE',
            'en-GB',
        ];
    }

    /**
     * @param string $locale
     *
     * @return Locale
     */
    public static function createFromString($locale)
    {
        return new self($locale);
    }
}
