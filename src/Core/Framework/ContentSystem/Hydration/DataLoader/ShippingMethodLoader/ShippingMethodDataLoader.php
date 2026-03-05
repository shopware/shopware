<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ShippingMethodLoader;

use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available shipping methods via AbstractShippingMethodRoute.
 *
 * Config:
 * - associations: Additional associations to load
 * - onlyAvailable: Only return available shipping methods (default: true)
 *
 * @internal
 *
 * @final
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

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        $criteria = new Criteria();
        $onlyAvailable = true;

        if ($config instanceof ShippingMethodLoaderConfig) {
            foreach ($config->associations as $association) {
                $criteria->addAssociation($association);
            }
            $onlyAvailable = $config->onlyAvailable;
        }

        // Clone request to set onlyAvailable parameter
        $clonedRequest = clone $request;
        $clonedRequest->query->set('onlyAvailable', $onlyAvailable);

        $response = $this->shippingMethodRoute->load($clonedRequest, $context, $criteria);

        // ShippingMethodRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getShippingMethods());
    }
}
