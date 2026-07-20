<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Validation\Constraint;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @internal
 */
#[Package('framework')]
class RouteNotBlockedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly RouteBlocklistService $blocklistService
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RouteNotBlocked) {
            throw RoutingException::unexpectedType($constraint, RouteNotBlockedValidator::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            $this->context->buildViolation(RouteNotBlocked::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        if (!$this->blocklistService->isPathBlocked($value)) {
            return;
        }

        $normalizedPath = '/' . trim($value, '/');

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('path', $this->formatValue($value))
            ->setParameter('blockedSegment', $this->formatValue($normalizedPath))
            ->setCode(RouteNotBlocked::ROUTE_BLOCKED)
            ->addViolation();
    }
}
