<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister\Extension;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<void>
 */
#[Package('framework')]
final class RuleConditionDeactivateExtension extends Extension
{
    public const NAME = 'app.rule-condition-persister.deactivate';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The ID of the app whose rule condition scripts should be deactivated
         */
        public readonly string $appId,
        /**
         * @public
         *
         * @description The current Shopware context
         */
        public readonly Context $context,
    ) {
    }
}
