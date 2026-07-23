<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Client;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * OAuth integrations should rely on {@see ClientEntityInterface} instead of this concrete Shopware class.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class ApiClient implements ClientEntityInterface
{
    use ClientTrait;

    private readonly bool $confidential;

    /**
     * @param non-empty-string $identifier
     */
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'confidential', newType: 'bool', description: 'The parameter becomes required and non-nullable and moves to position three, before $name, so $name can remain optional.')]
    public function __construct(
        private readonly string $identifier,
        private readonly bool $writeAccess,
        string $name = '',
        ?bool $confidential = null,
    ) {
        $this->name = $name;

        if ($confidential === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Parameter "confidential" will be required and not nullable in the next major');

            $this->confidential = true;
        } else {
            $this->confidential = $confidential;
        }
    }

    public function getWriteAccess(): bool
    {
        return $this->writeAccess;
    }

    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function isConfidential(): bool
    {
        return $this->confidential;
    }
}
