<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;

/**
 * @ deprecated tag:v6.6.0 - Will be removed. Use Shopware\Core\Framework\Script\ScriptException instead
 */
#[Package('core')]
class NoHookServiceFactoryException extends \RuntimeException
{
    public function __construct(string $service)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0', 'Use Shopware\Core\Framework\Script\ScriptException instead')
        );

        parent::__construct(sprintf('Service "%s" must extend the abstract "%s" so that this service may also be used in scripts.', $service, HookServiceFactory::class));
    }
}
