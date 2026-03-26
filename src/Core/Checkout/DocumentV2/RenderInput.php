<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class RenderInput implements \JsonSerializable
{
    // allows json_encode and to decode object via json serializer
    use JsonSerializableTrait;

    /**
     * @var array<string, RenderData>
     */
    private array $data = [];

    public function __construct(
        public readonly string $docType,
        public readonly string $docNumber,
        public readonly OrderEntity $order,
    ) {
    }

    public function setInput(string $key, RenderData $data): void
    {
        $this->data[$key] = $data;
    }

    public function getInput(string $key): ?RenderData
    {
        return $this->data[$key] ?? null;
    }
}
