<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\DataAbstractionLayer;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer\MediaFileExtensionWriteValidatorTest
 */
#[Package('discovery')]
class MediaFileExtensionWriteValidator implements EventSubscriberInterface
{
    final public const MEDIA_ILLEGAL_FILE_EXTENSION = 'MEDIA_ILLEGAL_FILE_EXTENSION';

    public function __construct(
        private readonly MediaFileExtensionListProvider $extensionListProvider,
        private readonly WriteOutcome $writeOutcome,
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
        $media = $this->writeOutcome->resultingRows($event->getCommandsForEntity(MediaDefinition::ENTITY_NAME), ['file_extension', 'private']);
        if ($media === []) {
            return;
        }

        $allowedPublic = $this->extensionListProvider->getAllowedExtensions(false, $event->getContext());
        $allowedPrivate = $this->extensionListProvider->getAllowedExtensions(true, $event->getContext());

        foreach ($media as $row) {
            $extension = $row->getResultingValue('file_extension');
            if (!\is_string($extension) || $extension === '') {
                continue;
            }

            $allowed = $row->getResultingValue('private') ? $allowedPrivate : $allowedPublic;
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

            $event->getExceptions()->add(new WriteConstraintViolationException($violations, $row->path));
        }
    }
}
