<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer;

use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Shopware\Core\Content\ImportExport\Exception\LogNotWritableException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.3.0 can be replaced with Definition::getProtections()
 */
class ImportExportLogValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @internal
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $ids = [];
        $writeCommands = $event->getCommands();

        foreach ($writeCommands as $command) {
            if ($command->getDefinition()->getClass() === ImportExportLogDefinition::class
                && $event->getContext()->getScope() !== Context::SYSTEM_SCOPE
            ) {
                $ids[] = $command->getPrimaryKey()['id'];
            }
        }

        if (!empty($ids)) {
            $event->getExceptions()->add(new LogNotWritableException($ids));
        }
    }
}
