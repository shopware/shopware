<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
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
     * @param array<string, RenderData> $data
     */
    public function __construct(
        public string $documentType,
        public string $documentNumber,
        public OrderEntity $order,
        private array $data = [],
        private ?Context $context = null,
    ) {
    }

    public function getData(string $key): ?RenderData
    {
        return $this->data[$key] ?? null;
    }

    /**
     * @template T of RenderData
     *
     * @param class-string<T> $expected
     *
     * @throws DocumentV2Exception
     */
    public function requireData(string $key, string $expected): RenderData
    {
        $data = $this->getData($key);

        if (!$data instanceof $expected) {
            throw DocumentV2Exception::unknownRenderData($key, $expected);
        }

        return $data;
    }

    /**
     * Returns the language aware render context for this generation run.
     * The language chain is prepended with the order's language.
     * Does fallback to default context, if no context was injected during creation.
     */
    public function getContext(): Context
    {
        return $this->context ?? Context::createDefaultContext();
    }

    /**
     * @return array<string, RenderData>
     */
    public function getAllData(): array
    {
        return $this->data;
    }
}
