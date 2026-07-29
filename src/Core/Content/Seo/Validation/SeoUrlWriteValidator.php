<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Validation;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Ensures that SEO paths written through the DAL (e.g. via the Admin API)
 * pass the same character-allowlist that the admin form and SEO controller
 * already enforce, so that values such as "seo/url%/1" cannot be persisted.
 *
 * @internal
 */
#[Package('inventory')]
class SeoUrlWriteValidator implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $constraint = new ValidSeoPathInfo();

        foreach ($event->getCommands() as $command) {
            if (!$command instanceof InsertCommand && !$command instanceof UpdateCommand) {
                continue;
            }

            if ($command->getEntityName() !== SeoUrlDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();

            if (!\array_key_exists('seo_path_info', $payload)) {
                continue;
            }

            $seoPathInfo = $payload['seo_path_info'];

            if ($seoPathInfo === null || $seoPathInfo === '') {
                continue;
            }

            $violationList = new ConstraintViolationList();

            if (!\is_string($seoPathInfo)) {
                $violationList->add($this->buildViolation(
                    ValidSeoPathInfo::INVALID_TYPE_MESSAGE,
                    ['{{ path }}' => null],
                    '/seoPathInfo',
                    (string) $seoPathInfo,
                    ValidSeoPathInfo::INVALID_CHARACTERS
                ));

                $event->getExceptions()->add(new WriteConstraintViolationException($violationList, $command->getPath()));

                continue;
            }

            if (!ValidSeoPathInfo::containsDisallowedCharacters($seoPathInfo)) {
                continue;
            }

            $violationList->add($this->buildViolation(
                $constraint->getMessage(),
                ['{{ path }}' => \sprintf('"%s"', $seoPathInfo)],
                '/seoPathInfo',
                $seoPathInfo,
                ValidSeoPathInfo::INVALID_CHARACTERS
            ));

            $event->getExceptions()->add(new WriteConstraintViolationException($violationList, $command->getPath()));
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        string $propertyPath,
        string $invalidValue,
        string $code
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            \str_replace(\array_keys($parameters), \array_map(static fn ($value) => (string) $value, \array_values($parameters)), $messageTemplate),
            $messageTemplate,
            $parameters,
            null,
            $propertyPath,
            $invalidValue,
            null,
            $code
        );
    }
}
