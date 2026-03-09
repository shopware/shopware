<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\SalesChannel\AbstractCurrencyRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available currencies via AbstractCurrencyRoute.
 *
 * Config:
 * - associations: Additional associations to load
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class CurrencyDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'currency';

    public function __construct(
        private readonly AbstractCurrencyRoute $currencyRoute,
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

        if ($config instanceof CurrencyLoaderConfig) {
            foreach ($config->associations as $association) {
                $criteria->addAssociation($association);
            }
        }

        $response = $this->currencyRoute->load($request, $context, $criteria);

        // CurrencyRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getCurrencies());
    }
}
