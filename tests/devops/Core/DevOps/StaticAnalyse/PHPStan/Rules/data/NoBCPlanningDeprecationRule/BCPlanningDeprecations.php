<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-final - Will become final
 */
class BCPlanningDeprecations
{
    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return static
     */
    public function returnTypeChange(): self
    {
        return $this;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $states will be added
     */
    public function newOptionalParameter(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - reason:parameter-default-change - parameter $scope will use a new default value
     */
    public function parameterDefaultChange(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-subscriber - Subscriber will be removed
     */
    public function actualDeprecationIsAllowed(): void
    {
    }

    /**
     * @deprecated tag:v6.8.0 - reason:exception-change - Will throw UtilException instead
     */
    public function exceptionChange(): void
    {
    }
}
