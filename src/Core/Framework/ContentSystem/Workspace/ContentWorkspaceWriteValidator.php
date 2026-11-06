<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Workspace;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
class ContentWorkspaceWriteValidator implements EventSubscriberInterface
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== ContentWorkspaceDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();
            if (isset($payload['configuration'])) {
                $violations = $this->validateConfiguration(json_decode($payload['configuration'], true, 512, \JSON_THROW_ON_ERROR));
                if ($violations->count() > 0) {
                    $event->getExceptions()->add(new WriteConstraintViolationException(new ConstraintViolationList($violations), $command->getPath() . '/configuration'));
                }
            }
        }
    }

    public function validateConfiguration(mixed $configuration): ConstraintViolationListInterface
    {
        $uuid = static fn () => new Assert\Sequentially([new Assert\Type('string'), new Assert\Length(exactly: 32), new Assert\Regex('/^[0-9a-f]{32}$/i')]);
        $number = static fn (float $min, float $max) => new Assert\Sequentially([
            new Assert\NotNull(), new Assert\Type('finite-number'), new Assert\Range(min: $min, max: $max),
        ]);

        return $this->validator->validate($configuration, [
            new Assert\NotNull(),
            new Assert\Collection(fields: [
                'frames' => [
                    new Assert\NotNull(), new Assert\Type('list'), new Assert\Unique(fields: ['layoutId']),
                    new Assert\All([
                        new Assert\NotNull(),
                        new Assert\Collection(fields: [
                            'layoutId' => [new Assert\NotBlank(), $uuid()],
                            'x' => [new Assert\NotNull(), new Assert\Type('finite-number')],
                            'y' => [new Assert\NotNull(), new Assert\Type('finite-number')],
                            'width' => $number(320, 2400),
                            'height' => $number(280, 1800),
                            'viewport' => [new Assert\NotNull(), new Assert\Choice(choices: ['mobile', 'tablet-landscape', 'desktop', 'custom'])],
                            'previewEntityId' => $uuid(),
                        ]),
                    ]),
                ],
                'previewSalesChannelId' => $uuid(),
                'canvas' => [new Assert\NotNull(), new Assert\Collection(fields: [
                    'panX' => [new Assert\NotNull(), new Assert\Type('finite-number')],
                    'panY' => [new Assert\NotNull(), new Assert\Type('finite-number')],
                    'zoom' => $number(0.1, 1.5),
                ])],
            ]),
        ]);
    }
}
