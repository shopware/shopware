<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\RequestStack;

#[Package('core')]
class TemplateScopeDetector
{
    public const SCOPES_ATTRIBUTE = '_templateScopes';
    public const DEFAULT_SCOPE = 'default';

    /**
     * @internal
     */
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return [self::DEFAULT_SCOPE];
        }

        $scope = $request->attributes->get(self::SCOPES_ATTRIBUTE);
        if (\is_string($scope)) {
            return [$scope];
        }

        if (\is_array($scope)) {
            return $scope;
        }

        if (!$scope) {
            return [self::DEFAULT_SCOPE];
        }

        throw AdapterException::invalidTemplateScope($scope);
    }
}
