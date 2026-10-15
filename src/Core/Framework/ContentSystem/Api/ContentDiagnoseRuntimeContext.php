<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Optional entity context for diagnosing runtime mapping outcomes against real Storefront data.
 *
 * @internal
 */
#[Package('framework')]
final class ContentDiagnoseRuntimeContext
{
    /**
     * @param array<string, mixed> $queryParameters
     */
    public function __construct(
        #[Assert\NotBlank]
        public readonly string $entityType,
        #[Assert\NotBlank]
        public readonly string $entityId,
        #[Assert\NotBlank]
        public readonly string $salesChannelId,
        public readonly ?string $languageId = null,
        public readonly ?string $currencyId = null,
        public readonly ?string $domainId = null,
        public readonly ?string $customerId = null,
        #[Assert\Callback([self::class, 'rejectNonStringQueryParameterNames'])]
        public readonly array $queryParameters = [],
    ) {
    }

    /**
     * @param array<array-key, mixed>|mixed $value
     */
    public static function rejectNonStringQueryParameterNames(mixed $value, ExecutionContextInterface $context): void
    {
        if (!\is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $key) {
            if (\is_string($key)) {
                continue;
            }

            $context->buildViolation('Query parameter name "{{ name }}" must be a string.')
                ->setParameter('{{ name }}', (string) $key)
                ->addViolation();
        }
    }
}
