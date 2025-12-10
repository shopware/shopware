<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentContext
{
    /**
     * @var array<value-of<ConsentScope>, string>
     */
    private array $scopes = [];

    public function add(ConsentScope $scope, string $identifier): self
    {
        $this->scopes[$scope->value] = $identifier;

        return $this;
    }

    public function getIdentifierForScope(ConsentScope $scope): ?string
    {
        return $this->scopes[$scope->value] ?? null;
    }
}
