<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\StorefrontContext;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * The context values approved for data mapping. Cache tracking cannot currently invalidate changes to the
 * currency symbol or language locale, so a render using this source disables page caching.
 *
 * @internal
 *
 * @extends AbstractContentDataLoader<ArrayStruct>
 */
#[Package('framework')]
final class StorefrontContextLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'storefront_context';

    public const REQUIREMENT_KEY = '__storefront_context_mapping';

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public static function requirement(): DataRequirement
    {
        return new DataRequirement(self::REQUIREMENT_KEY, self::SOURCE, new StorefrontContextLoaderConfig());
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request,
    ): ContentDataLoaderResult {
        return ContentDataLoaderResult::uncacheable(new ArrayStruct([
            'currency.isoCode' => $context->getCurrency()->getIsoCode(),
            'currency.symbol' => $context->getCurrency()->getSymbol(),
            'language.localeCode' => $context->getLanguageInfo()->localeCode,
            'tax.state' => $context->getTaxState(),
        ]));
    }
}
