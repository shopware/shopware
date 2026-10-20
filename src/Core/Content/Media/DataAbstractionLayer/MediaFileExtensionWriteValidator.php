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
                && \array_key_exists('file_extension', $command->getPayload())
        );

        if ($commands === []) {
            return;
        }

        $allowedPublic = $this->extensionListProvider->getAllowedExtensions(false, $event->getContext());
        $allowedPrivate = $this->extensionListProvider->getAllowedExtensions(true, $event->getContext());

        $privateFilesystemMapping = $this->getPrivateFilesystemMapping($commands);

        foreach ($commands as $command) {
            $extension = $command->getPayload()['file_extension'] ?? null;

            if (!\is_string($extension) || $extension === '') {
                continue;
            }

            $allowed = $this->resolveIsPrivate($command, $privateFilesystemMapping) ? $allowedPrivate : $allowedPublic;

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

    /**
     * @param array<string, string> $privateFilesystemMapping
     */
    private function resolveIsPrivate(WriteCommand $command, array $privateFilesystemMapping): bool
    {
        $payload = $command->getPayload();
        if (\array_key_exists('private', $payload)) {
            return (bool) $payload['private'];
        }

        if ($command instanceof InsertCommand) {
            return false;
        }

        $id = $command->getDecodedPrimaryKey()['id'] ?? null;

        if (!\is_string($id) || !\array_key_exists($id, $privateFilesystemMapping)) {
            return false;
        }

        return (bool) $privateFilesystemMapping[$id];
    }

    /**
     * @param WriteCommand[] $commands
     *
     * @return array<string, string>
     */
    private function getPrivateFilesystemMapping(array $commands): array
    {
        $commands = array_filter(
            $commands,
            static fn (WriteCommand $command): bool => $command instanceof UpdateCommand
                && !\array_key_exists('private', $command->getPayload())
        );

        $ids = array_filter(array_map(
            static fn (WriteCommand $command): ?string => $command->getPrimaryKey()['id'] ?? null,
            $commands
        ));

        if ($ids === []) {
            return [];
        }

        return $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(`id`)), `private` FROM `media` WHERE `id` IN (:ids)',
            ['ids' => array_values($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
