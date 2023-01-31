<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
abstract class AbstractAppLifecycle
{
    abstract public function getDecorated(): AbstractAppLifecycle;

    abstract public function install(Manifest $manifest, bool $activate, Context $context): void;

    abstract public function update(Manifest $manifest, array $app, Context $context): void;

    abstract public function delete(string $appName, array $app, Context $context, bool $keepUserData = false): void;
}
