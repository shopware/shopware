<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Shared immutable input handed to all renderers during one generation run.
 *
 * It bundles the order snapshot, the final document number and all provider DTOs so renderers
 * can consume prepared data without reloading or recalculating it.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class RenderInput
{
    /**
     * @param array<string, AbstractRenderData> $data
     */
    public function __construct(
        public string $documentType,
        public string $documentNumber,
        public OrderEntity $order,
        private array $data = [],
    ) {
    }

    public function getData(string $key): ?AbstractRenderData
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @template T of AbstractRenderData
     *
     * @param class-string<T> $expected
     *
     * @throws DocumentV2Exception
     *
     * @return T
     */
    public function requireData(string $key, string $expected): AbstractRenderData
    {
        $data = $this->getData($key);

        if (!$data instanceof $expected) {
            throw DocumentV2Exception::unknownRenderData($key, $expected);
        }

        return $data;
    }

    /**
     * @return array<string, AbstractRenderData>
     */
    public function getAllData(): array
    {
        return $this->data;
    }
}
