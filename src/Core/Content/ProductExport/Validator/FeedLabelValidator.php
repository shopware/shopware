<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Validator;

use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
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
class FeedLabelValidator implements EventSubscriberInterface
{
    private const FEED_LABEL_PATTERN = '/^[A-Z0-9_-]{1,20}$/';

    private const ERROR_CODE = 'PRODUCT_EXPORT__INVALID_FEED_LABEL_FORMAT';

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== ProductExportDefinition::ENTITY_NAME) {
                continue;
            }

            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            $payload = $command->getPayload();
            if (!\array_key_exists('feed_label', $payload)) {
                continue;
            }

            $value = $payload['feed_label'];
            if ($value === null || $value === '') {
                continue;
            }

            if (\is_string($value) && \preg_match(self::FEED_LABEL_PATTERN, $value) === 1) {
                continue;
            }

            $violations = new ConstraintViolationList();
            $violations->add(new ConstraintViolation(
                'The feed label may only contain uppercase letters (A-Z), digits (0-9), hyphens, or underscores, up to 20 characters.',
                null,
                [],
                null,
                '/feedLabel',
                $value,
                null,
                self::ERROR_CODE
            ));

            $event->getExceptions()->add(
                new WriteConstraintViolationException($violations, $command->getPath())
            );
        }
    }
}
