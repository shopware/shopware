<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class RenderInput
{
    /**
     * @param array<string, RenderData> $data
     */
    public function __construct(
        private string $documentType,
        private string $documentNumber,
        private OrderEntity $order,
        private array $data = []
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

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getDocumentNumber(): string
    {
        return $this->documentNumber;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    /**
     * @return array<string, RenderData>
     */
    public function getAllData(): array
    {
        return $this->data;
    }
}
