<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;

abstract class AbstractAppLifecycle
{
    abstract public function getDecorated(): AbstractAppLifecycle;

    abstract public function install(Manifest $manifest, bool $activate, Context $context): void;

    abstract public function update(Manifest $manifest, array $app, Context $context): void;

    abstract public function delete(string $appName, array $app, Context $context): void;
}
