<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Hydration\DataLoader\PaymentMethodLoader;

use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available payment methods via AbstractPaymentMethodRoute.
 *
 * Config:
 * - associations: Additional associations to load
 * - onlyAvailable: Only return available payment methods (default: true)
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class PaymentMethodDataLoader extends AbstractContentDataLoader
{
    public function __construct(
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
    ) {
    }

    public function getDecorated(): AbstractContentDataLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public static function getRequirementType(): string
    {
        return 'payment_method';
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

        if ($config instanceof PaymentMethodLoaderConfig) {
            foreach ($config->associations as $association) {
                $criteria->addAssociation($association);
            }
            $onlyAvailable = $config->onlyAvailable;
        }

        // Clone request to set onlyAvailable parameter
        $clonedRequest = clone $request;
        $clonedRequest->query->set('onlyAvailable', $onlyAvailable);

        $response = $this->paymentMethodRoute->load($clonedRequest, $context, $criteria);

        // PaymentMethodRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getPaymentMethods());
    }
}
