<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class NonLanguageAwareEvent implements FlowEventAware
{
    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
