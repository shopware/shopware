<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Validation\Constraint;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * @internal
 *
 * @codeCoverageIgnore The class only has a simple getter, there's no real logic to test
 */
#[Package('framework')]
class RouteNotBlocked extends Constraint
{
    final public const INVALID_TYPE_MESSAGE = 'This value should be of type string.';
    final public const ROUTE_BLOCKED = 'FRAMEWORK__ROUTE_BLOCKED';

    protected const ERROR_NAMES = [
        self::ROUTE_BLOCKED => 'FRAMEWORK__ROUTE_BLOCKED',
    ];

    protected string $message = 'FRAMEWORK__ROUTE_BLOCKED_MESSAGE';

    /**
     * @param array<string, mixed>|null $options
     */
    public function __construct(
        ?array $options = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
