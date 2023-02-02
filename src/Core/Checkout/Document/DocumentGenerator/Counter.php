<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\DocumentGenerator;

use Shopware\Core\Framework\Feature;

class Counter
{
    private int $counter = 0;

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    private int $page = 1;

    public function getCounter(): int
    {
        return $this->counter;
    }

    public function increment(): void
    {
        $this->counter = $this->counter + 1;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    public function getPage(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        return $this->page;
    }

    /**
     * @deprecated tag:v6.5.0 - Will be removed
     */
    public function incrementPage(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0')
        );

        $this->page = $this->page + 1;
    }
}
