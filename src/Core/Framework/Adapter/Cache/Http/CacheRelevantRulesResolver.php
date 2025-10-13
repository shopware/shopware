<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http;

use Shopware\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @final
 */
#[Package('framework')]
readonly class CacheRelevantRulesResolver
{
    /**
     * @internal
     */
    public function __construct(
        private ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @return list<string> List of rule IDs which should be considered for the HTTP Cache in the context cookie / header
     */
    public function resolveRuleAreas(Request $request, SalesChannelContext $context): array
    {
        $ruleIdsExtension = new ResolveCacheRelevantRuleIdsExtension($request, [RuleAreas::PRODUCT_AREA], $context);

        /** @var list<string> $ruleAreas */
        $ruleAreas = $this->extensions->publish(
            name: ResolveCacheRelevantRuleIdsExtension::NAME,
            extension: $ruleIdsExtension,
            function: function (Request $request, array $ruleAreas, SalesChannelContext $salesChannelContext): array {
                return $ruleAreas;
            },
        );

        return $ruleAreas;
    }
}
