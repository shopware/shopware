<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http\Extension;

use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @extends Extension<array<string>>
 */
#[Package('framework')]
final class ResolveCacheRelevantRuleIdsExtension extends Extension
{
    public const NAME = 'cache-response.resolve-rule-areas';

    /**
     * @internal Shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The HTTP request object
         */
        public readonly Request $request,

        /**
         * @public
         *
         * @description RuleAreas which should be considered for the HTTP Cache in the context cookie
         *
         * @var list<string>
         */
        public array $ruleAreas,

        /**
         * @public
         *
         * @description The sales channel context
         */
        public readonly SalesChannelContext $salesChannelContext,
    ) {
    }
}
