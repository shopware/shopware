<?php declare(strict_types=1);

namespace Shopware\Translation\Event;

use Symfony\Component\EventDispatcher\Event;

class ImportFinishEvent extends Event
{
    const EVENT_NAME = 'translation.import.finish';
}
