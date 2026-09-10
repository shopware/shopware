<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Envelope DTO for the update-element-properties mutation action.
 *
 * @internal
 */
#[Package('framework')]
final class UpdateElementPropertiesRequest
{
    /**
     * @param array<int|string, mixed> $layout
     * @param array<string, mixed> $values
     * @param list<string> $removeKeys
     */
    public function __construct(
        public readonly string $elementId,
        #[Assert\Type('array')]
        public readonly array $layout = [],
        #[Assert\Type('array')]
        #[Assert\Callback([self::class, 'rejectEmptyRequest'])]
        public readonly array $values = [],
        #[Assert\Type('array')]
        #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
        #[Assert\Unique]
        public readonly array $removeKeys = [],
        public readonly ?string $rootSource = null,
    ) {
    }

    /**
     * A request writing nothing and removing nothing has no edit to apply, so it is refused here rather than
     * answered with an unchanged tree. `removeKeys` is read off the containing DTO, which
     * `$context->getObject()` returns while a property-level constraint runs.
     */
    public static function rejectEmptyRequest(mixed $value, ExecutionContextInterface $context): void
    {
        if ($value !== []) {
            return;
        }

        $request = $context->getObject();

        if (!$request instanceof self || $request->removeKeys !== []) {
            return;
        }

        $context->buildViolation('An update-element-properties request must carry at least one entry in "values" or "removeKeys" (updateElementPropertiesEmpty).')
            ->addViolation();
    }
}
