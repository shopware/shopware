<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Struct;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Framework\Log\Package;

/**
 * In-memory storage for rendered intermediate and final format outputs.
 *
 * RenderState exists only for the duration of one generation run and lets dependent renderers
 * reuse already rendered formats without persisting every intermediate artifact.
 * Renderers must declare every format they read from this state as a dependency.
 *
 * @internal
 */
#[Package('after-sales')]
final class RenderState
{
    /**
     * @var array<string, RenderResult>
     */
    private array $results = [];

    public function has(string $format): bool
    {
        return isset($this->results[$format]);
    }

    public function get(string $format): ?RenderResult
    {
        return $this->results[$format] ?? null;
    }

    /**
     * Returns the result for a format or throws if it was not rendered before.
     * The requested format must be declared as a renderer dependency before calling this method.
     *
     * @throws DocumentV2Exception
     */
    public function require(string $format): RenderResult
    {
        if (!$this->has($format)) {
            throw DocumentV2Exception::unknownRenderResult($format);
        }

        return $this->results[$format];
    }

    /**
     * Stores a format result exactly once.
     *
     * @throws DocumentV2Exception
     */
    public function add(RenderResult $result): void
    {
        if ($this->has($result->format)) {
            throw DocumentV2Exception::duplicateRenderResult($result->format);
        }

        $this->results[$result->format] = $result;
    }

    /**
     * @return array<string, RenderResult>
     */
    public function getAll(): array
    {
        return $this->results;
    }
}
