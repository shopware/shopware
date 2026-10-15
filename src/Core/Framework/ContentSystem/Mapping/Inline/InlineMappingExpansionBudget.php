<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping\Inline;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * Bounds inline output across one render. Bytes assembled for a property that later fails are not refunded,
 * so repeatedly discarding oversized properties cannot bypass the render's allowance.
 *
 * @internal
 */
#[Package('framework')]
final class InlineMappingExpansionBudget
{
    public const MAX_PROPERTY_BYTES = 1_048_576;

    public const MAX_RENDER_BYTES = 4_194_304;

    private int $remainingPropertyBytes = self::MAX_PROPERTY_BYTES;

    private int $remainingRenderBytes = self::MAX_RENDER_BYTES;

    public function beginProperty(): void
    {
        $this->remainingPropertyBytes = self::MAX_PROPERTY_BYTES;

        if ($this->remainingRenderBytes === 0) {
            throw ContentSystemException::inlineMappingExpansionTooLarge(self::MAX_RENDER_BYTES, 'render');
        }
    }

    /**
     * Checks before escaping or appending. The render is exhausted when its allowance is the tighter bound,
     * even if the rejected value did not fit: later properties must not keep resolving against that remainder.
     */
    public function assertFits(int $bytes): void
    {
        if ($bytes > $this->remainingRenderBytes && $this->remainingRenderBytes <= $this->remainingPropertyBytes) {
            $this->remainingRenderBytes = 0;

            throw ContentSystemException::inlineMappingExpansionTooLarge(self::MAX_RENDER_BYTES, 'render');
        }

        if ($bytes > $this->remainingPropertyBytes) {
            throw ContentSystemException::inlineMappingExpansionTooLarge(self::MAX_PROPERTY_BYTES, 'property');
        }
    }

    public function consume(int $bytes): void
    {
        $this->assertFits($bytes);
        $this->remainingPropertyBytes -= $bytes;
        $this->remainingRenderBytes -= $bytes;
    }
}
