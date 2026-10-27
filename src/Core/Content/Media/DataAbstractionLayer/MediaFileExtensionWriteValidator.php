<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @phpstan-type StoredMedia array<string, array{private: string|int|bool|null, file_extension: string|null}>
 */
#[Package('discovery')]
class MediaFileExtensionWriteValidator implements EventSubscriberInterface
{
    final public const MEDIA_ILLEGAL_FILE_EXTENSION = 'MEDIA_ILLEGAL_FILE_EXTENSION';

    public function __construct(
        private readonly MediaFileExtensionListProvider $extensionListProvider,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $commands = array_filter(
            $event->getCommandsForEntity(MediaDefinition::ENTITY_NAME),
            static fn (WriteCommand $command): bool => ($command instanceof InsertCommand || $command instanceof UpdateCommand)
                && (\array_key_exists('file_extension', $command->getPayload()) || self::becomesPublic($command))
        );

        if ($commands === []) {
            return;
        }

        $allowedPublic = $this->extensionListProvider->getAllowedExtensions(false, $event->getContext());
        $allowedPrivate = $this->extensionListProvider->getAllowedExtensions(true, $event->getContext());

        $storedMedia = $this->getStoredMedia($commands);

        foreach ($commands as $command) {
            $payload = $command->getPayload();

            // A media item that becomes public keeps its stored extension, so that extension must be allowed in public media.
            $extension = \array_key_exists('file_extension', $payload)
                ? $payload['file_extension']
                : $this->resolveStoredExtension($command, $storedMedia);

            if (!\is_string($extension) || $extension === '') {
                continue;
            }

            $allowed = $this->resolveIsPrivate($command, $storedMedia) ? $allowedPrivate : $allowedPublic;

            if (\in_array(mb_strtolower($extension), $allowed, true)) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $violations->add(new ConstraintViolation(
                \sprintf('The file extension "%s" is not allowed.', $extension),
                'The file extension "{{ extension }}" is not allowed.',
                ['{{ extension }}' => $extension],
                null,
                '/fileExtension',
                $extension,
                code: self::MEDIA_ILLEGAL_FILE_EXTENSION
            ));

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $command->getPath()));
        }
    }

    private static function becomesPublic(WriteCommand $command): bool
    {
        $payload = $command->getPayload();

        return $command instanceof UpdateCommand
            && \array_key_exists('private', $payload)
            && !$payload['private'];
    }

    /**
     * @param StoredMedia $storedMedia
     */
    private function resolveIsPrivate(WriteCommand $command, array $storedMedia): bool
    {
        $payload = $command->getPayload();
        if (\array_key_exists('private', $payload)) {
            return (bool) $payload['private'];
        }

        if ($command instanceof InsertCommand) {
            return false;
        }

        $id = $command->getDecodedPrimaryKey()['id'] ?? null;

        if (!\is_string($id) || !\array_key_exists($id, $storedMedia)) {
            return false;
        }

        return (bool) $storedMedia[$id]['private'];
    }

    /**
     * @param StoredMedia $storedMedia
     */
    private function resolveStoredExtension(WriteCommand $command, array $storedMedia): ?string
    {
        $id = $command->getDecodedPrimaryKey()['id'] ?? null;

        if (!\is_string($id) || !\array_key_exists($id, $storedMedia)) {
            return null;
        }

        return $storedMedia[$id]['file_extension'];
    }

    /**
     * @param WriteCommand[] $commands
     *
     * @return StoredMedia
     */
    private function getStoredMedia(array $commands): array
    {
        $commands = array_filter(
            $commands,
            static fn (WriteCommand $command): bool => $command instanceof UpdateCommand
                && (!\array_key_exists('private', $command->getPayload()) || !\array_key_exists('file_extension', $command->getPayload()))
        );

        $ids = array_filter(array_map(
            static fn (WriteCommand $command): ?string => $command->getPrimaryKey()['id'] ?? null,
            $commands
        ));

        if ($ids === []) {
            return [];
        }

        /** @var StoredMedia $storedMedia */
        $storedMedia = $this->connection->fetchAllAssociativeIndexed(
            'SELECT LOWER(HEX(`id`)) AS `id`, `private`, `file_extension` FROM `media` WHERE `id` IN (:ids)',
            ['ids' => array_values($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $storedMedia;
    }
}
