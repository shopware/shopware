<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister\Extension;

use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Extensions\Extension;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @extends Extension<void>
 */
#[Package('framework')]
final class RuleConditionPersistExtension extends Extension
{
    public const NAME = 'app.rule-condition-persister.persist';

    /**
     * @internal shopware owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The app lifecycle context containing the manifest, app entity, and Shopware context
         */
        public readonly AppLifecycleContext $context,
    ) {
    }
}
