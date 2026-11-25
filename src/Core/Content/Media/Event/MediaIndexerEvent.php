<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Shopware no longer dispatches MediaIndexerEvent. Subscribe to media.written, media.deleted or ThumbnailGeneratedEvent as appropriate.
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaIndexerEvent extends NestedEvent
{
    private const DEPRECATION_MESSAGE = 'Shopware no longer dispatches MediaIndexerEvent. Subscribe to media.written, media.deleted or ThumbnailGeneratedEvent as appropriate.';

    /**
     * @param list<string> $ids
     * @param list<string> $skip
     */
    public function __construct(
        private readonly array $ids,
        private readonly Context $context,
        private readonly array $skip = [],
    ) {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', self::DEPRECATION_MESSAGE);
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', self::DEPRECATION_MESSAGE);

        return $this->context;
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', self::DEPRECATION_MESSAGE);

        return $this->ids;
    }

    /**
     * @return list<string>
     */
    public function getSkip(): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', self::DEPRECATION_MESSAGE);

        return $this->skip;
    }
}
