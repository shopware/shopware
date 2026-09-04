<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Request payload for the content-layout preview action. Validates the envelope only:
 * the layout stays a raw array so the draft decoder remains the single decode path.
 *
 * @internal
 */
#[Package('framework')]
final class ContentPreviewRequest
{
    /**
     * @param array<int|string, mixed> $layout
     * @param array<string, mixed> $queryParameters
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('array')]
        public readonly array $layout,
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
        #[Assert\Type('array')]
        #[Assert\Callback([self::class, 'rejectNonStringQueryParameterNames'])]
        public readonly array $queryParameters = [],
    ) {
    }

    /**
     * PHP casts a numeric-string JSON member name to an integer array key, so `{"0": "x"}` arrives here as an
     * int-keyed entry the envelope cannot carry. Rejecting it at mint keeps the failure on the request that
     * caused it, which is the whole point: redemption already refused such an entry, but as a fault raised
     * against a token whose request is long gone. {@see ContentPreviewPayloadStore::stringKeyedField()} is
     * that older check and still runs first on the read, so this constraint is not what fires there.
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
