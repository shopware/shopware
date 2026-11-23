<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\File\FileNameValidator;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Util\ExternalMediaUrl;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\OutcomeRow;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Only the system may write relative paths; other writers may only reference external media by URL.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Media\Api\MediaPathWriteProtectionTest
 */
#[Package('discovery')]
class MediaPathWriteValidator implements EventSubscriberInterface
{
    private const PATH_ENTITIES = [MediaDefinition::ENTITY_NAME, MediaThumbnailDefinition::ENTITY_NAME];

    public function __construct(private readonly WriteOutcome $writeOutcome)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        if ($event->getContext()->getScope() === Context::SYSTEM_SCOPE) {
            return;
        }

        foreach (self::PATH_ENTITIES as $entityName) {
            $this->validatePaths($event, $entityName);
        }
        $this->validateFileNames($event);
    }

    /**
     * @throws MediaException
     */
    public static function validateStorageValue(string $entityName, string $propertyName, mixed $value, bool $allowNullPath): void
    {
        if ($propertyName === 'path' && \in_array($entityName, self::PATH_ENTITIES, true)) {
            if ($value === null && $allowNullPath) {
                return;
            }

            if (!\is_string($value) || !ExternalMediaUrl::isStrict($value)) {
                throw MediaException::illegalPath();
            }

            return;
        }

        if ($propertyName === 'fileName' && $entityName === MediaDefinition::ENTITY_NAME && \is_string($value)) {
            (new FileNameValidator())->validateFileName($value);
        }
    }

    private function validatePaths(PreWriteValidationEvent $event, string $entityName): void
    {
        foreach ($this->writeOutcome->resultingRows($event->getCommandsForEntity($entityName), ['path']) as $row) {
            $path = $row->getPayloadValue('path');

            if ($path !== $row->getStoredValue('path')) {
                $this->addViolationIfInvalid($event, $row, $entityName, 'path', $path);
            }
        }
    }

    private function validateFileNames(PreWriteValidationEvent $event): void
    {
        foreach ($this->writeOutcome->resultingRows($event->getCommandsForEntity(MediaDefinition::ENTITY_NAME), ['file_name']) as $row) {
            $fileName = $row->getPayloadValue('file_name');

            if (\is_string($fileName) && $fileName !== $row->getStoredValue('file_name')) {
                $this->addViolationIfInvalid($event, $row, MediaDefinition::ENTITY_NAME, 'fileName', $fileName);
            }
        }
    }

    private function addViolationIfInvalid(PreWriteValidationEvent $event, OutcomeRow $row, string $entityName, string $propertyName, mixed $value): void
    {
        try {
            self::validateStorageValue($entityName, $propertyName, $value, allowNullPath: $row->isNew);
        } catch (MediaException $exception) {
            $this->addViolation($event, $row, $exception->getErrorCode(), '/' . $propertyName, $exception->getMessage());
        }
    }

    private function addViolation(PreWriteValidationEvent $event, OutcomeRow $row, string $code, string $propertyPath, string $message): void
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $message, [], null, $propertyPath, null, code: $code));

        $event->getExceptions()->add(new WriteConstraintViolationException($violations, $row->path));
    }
}
