<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WebhookUrlWriteValidator implements EventSubscriberInterface
{
    public function __construct(private WebhookTargetValidator $targetValidator)
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
        foreach ($event->getCommands() as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            if ($command->getEntityName() !== WebhookDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();
            if (!\array_key_exists('url', $payload)) {
                continue;
            }

            $url = $payload['url'];
            if (!\is_string($url) || $this->targetValidator->validate($url) === null) {
                $event->getExceptions()->add(new WriteConstraintViolationException(
                    new ConstraintViolationList([
                        new ConstraintViolation(
                            'The webhook URL is not allowed by the configured webhook network policy.',
                            'The webhook URL is not allowed by the configured webhook network policy.',
                            [],
                            null,
                            '/url',
                            $url,
                        ),
                    ]),
                    $command->getPath()
                ));
            }
        }
    }
}
