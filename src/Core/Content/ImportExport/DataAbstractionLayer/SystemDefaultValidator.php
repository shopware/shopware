<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer;

use Shopware\Core\Content\ImportExport\Exception\DeleteDefaultProfileException;
use Shopware\Core\Content\ImportExport\ImportExportProfileDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\SystemDefaultValidatorTest
 */
#[Package('fundamentals@after-sales')]
class SystemDefaultValidator implements EventSubscriberInterface
{
    public function __construct(private readonly WriteOutcome $writeOutcome)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    /**
     * @internal
     *
     * @throws DeleteDefaultProfileException
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        $deletedDefaults = [];
        foreach ($this->writeOutcome->touchedRows($event->getCommandsForEntity(ImportExportProfileDefinition::ENTITY_NAME), ['system_default']) as $profile) {
            if ($profile->isDeleted && $profile->getStoredValue('system_default')) {
                $deletedDefaults[] = $profile->primaryKey['id'];
            }
        }

        if ($deletedDefaults !== []) {
            $event->getExceptions()->add(new DeleteDefaultProfileException($deletedDefaults));
        }
    }
}
