<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\Exception\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final class RenderState
{
    /**
     * @var array<string, RenderResult>
     */
    private array $results = [];

    public function has(DocumentFormat $format): bool
    {
        return isset($this->results[$format->value]);
    }

    public function get(DocumentFormat $format): RenderResult
    {
        return $this->results[$format->value];
    }

    /**
     * @throws DocumentV2Exception
     */
    public function require(DocumentFormat $format): RenderResult
    {
        if (!$this->has($format)) {
            throw DocumentV2Exception::unknownRenderResult($format);
        }

        return $this->get($format);
    }

    /**
     * @throws DocumentV2Exception
     */
    public function add(RenderResult $result): void
    {
        if ($this->has($result->getFormat())) {
            throw DocumentV2Exception::duplicateRenderResult($result->getFormat());
        }

        $this->results[$result->getFormat()->value] = $result;
    }

    /**
     * @return array<string, RenderResult>
     */
    public function getAll(): array
    {
        return $this->results;
    }
}
