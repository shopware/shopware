<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * The `config-schema.json` describes the `shopware.yaml` structure and should follow its changes.
 *
 * @internal
 */
#[Package('framework')]
class ShopwareYamlConfigSchemaHint
{
    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        $shopwareYamlTouched = $files->matches('*/shopware.yaml')->count() > 0;
        $configSchemaTouched = $files->matches('config-schema.json')->count() > 0;

        if ($shopwareYamlTouched && !$configSchemaTouched) {
            $context->warning('You updated the shopware.yaml, please consider to update the config-schema.json');
        }
    }
}
