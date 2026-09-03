<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\ContentSystem\DataLoader;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
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
 * Loads available payment methods via AbstractPaymentMethodRoute.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<PaymentMethodCollection>
 */
#[Package('framework')]
class PaymentMethodDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'payment_method';

    public function __construct(
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
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

        // Any ShopwareHttpException degrades the element to notFound(); everything else, such as a \TypeError
        // or a database driver failure, propagates. Why the catch is the covering ancestor and never an
        // enumerated union: src/Core/Framework/ContentSystem/Hydration/DataLoader/README.md#degradation-boundary
        // Known local throws: PaymentMethodRoute runs the PaymentMethodRouteHook app scripts through
        // ScriptExecutor, which rewraps any Throwable they raise as ScriptExecutionFailedException.
        try {
            $response = $this->paymentMethodRoute->load($clonedRequest, $context, $criteria);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // PaymentMethodRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getPaymentMethods());
    }
}
