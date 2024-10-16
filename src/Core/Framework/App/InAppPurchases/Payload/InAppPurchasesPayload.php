<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\InAppPurchases\Payload;

use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\Payload\SourcedPayloadInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal
 */
#[Package('checkout')]
class InAppPurchasesPayload implements SourcedPayloadInterface
{
    use JsonSerializableTrait;

    private Source $source;

    /**
     * @var list<string>
     */
    private array $purchases;

    /**
     * @param list<string> $purchases
     */
    public function __construct(array $purchases)
    {
        $this->purchases = $purchases;
    }

    /**
     * @return list<string>
     */
    public function getPurchases(): array
    {
        return $this->purchases;
    }

    /**
     * @param list<string> $purchases
     */
    public function setPurchases(array $purchases): void
    {
        $this->purchases = $purchases;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
