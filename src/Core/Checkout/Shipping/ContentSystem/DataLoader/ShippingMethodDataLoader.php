<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader;

use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available shipping methods via AbstractShippingMethodRoute.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<ShippingMethodCollection>
 */
#[Package('framework')]
class ShippingMethodDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'shipping_method';

    public function __construct(
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
            new ConfigKeySpecification('onlyAvailable', ConfigKeyKind::Literal, 'boolean', required: false, hasDefault: true, default: true),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        // Clone request to set onlyAvailable parameter
        $clonedRequest = clone $request;
        $clonedRequest->query->set('onlyAvailable', $inputs->bool('onlyAvailable'));

        // A failure Shopware modelled as an HTTP outcome degrades the element; anything beneath that line,
        // such as a \TypeError, an \AssertionError, or a database driver failure, propagates. Catch the
        // covering ancestor rather than an enumerated set: the reachable set is open, and a decorator can
        // rewrap a named class into an unnamed one. ShippingMethodRoute executes the ShippingMethodRouteHook
        // store-api route hook through ScriptExecutor, which rewraps every Throwable an app script raises as
        // ScriptExecutionFailedException, so no enumeration of the chain's own classes can be complete.
        try {
            $response = $this->shippingMethodRoute->load($clonedRequest, $context, $criteria);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // ShippingMethodRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getShippingMethods());
    }
}
