<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('inventory')]
class GaranLabelProductValidator implements EventSubscriberInterface
{
    public const VIOLATION_CODE = 'INVALID_GARAN_GUARANTEE_MONTHS';

    public const TERMS_URL_VIOLATION_CODE = 'INVALID_GARAN_GUARANTEE_TERMS_URL';

    /**
     * The statutory guarantee already covers the first 24 months, so a commercial guarantee only
     * says something beyond them: 30 is the lowest half-year value that qualifies.
     */
    public const MINIMUM_MONTHS = 30;

    /**
     * 50 years.
     */
    public const MAXIMUM_MONTHS = 600;

    public const STEP_MONTHS = 6;

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'validate',
        ];
    }

    public static function isValidDuration(int $guaranteeMonths): bool
    {
        return $guaranteeMonths >= self::MINIMUM_MONTHS
            && $guaranteeMonths <= self::MAXIMUM_MONTHS
            && $guaranteeMonths % self::STEP_MONTHS === 0;
    }

    public function validate(PreWriteValidationEvent $event): void
    {
        $violations = new ConstraintViolationList();

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== ProductDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();
            $guaranteeMonths = $payload['guarantee_months'] ?? null;

            if ($guaranteeMonths !== null && (!\is_int($guaranteeMonths) || !self::isValidDuration($guaranteeMonths))) {
                $violations->add(new ConstraintViolation(
                    'The GARAN guarantee duration must be empty or a half-year value between 30 and 600 months.',
                    'The GARAN guarantee duration must be empty or a half-year value between 30 and 600 months.',
                    [],
                    null,
                    $command->getPath() . '/guaranteeMonths',
                    $guaranteeMonths,
                    null,
                    self::VIOLATION_CODE
                ));
            }

            $termsUrl = $payload['guarantee_terms_url'] ?? null;

            // rendered as a link, so no `javascript:` or other schemes
            if ($termsUrl !== null && (!\is_string($termsUrl) || preg_match('~^https?://\S+$~iD', $termsUrl) !== 1)) {
                $violations->add(new ConstraintViolation(
                    'The GARAN guarantee terms URL must be empty or an http(s) URL.',
                    'The GARAN guarantee terms URL must be empty or an http(s) URL.',
                    [],
                    null,
                    $command->getPath() . '/guaranteeTermsUrl',
                    $termsUrl,
                    null,
                    self::TERMS_URL_VIOLATION_CODE
                ));
            }
        }

        if ($violations->count() > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }
}
