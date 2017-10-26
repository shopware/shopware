<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class PluginWidgetsNotesWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 's_plugin_widgets_notes.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_plugin_widgets_notes';
    }
}
