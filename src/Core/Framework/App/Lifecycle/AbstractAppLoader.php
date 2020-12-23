<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
abstract class AbstractAppLoader
{
    abstract public function getDecorated(): AbstractAppLoader;

    /**
     * @return Manifest[]
     */
    abstract public function load(): array;

    abstract public function getIcon(Manifest $app): ?string;

    /**
     * @deprecated tag:v6.4.0 will be made abstract and extending classes will need to provide an implementation
     */
    public function getConfiguration(AppEntity $app): ?array
    {
        $decorated = $this->getDecorated();

        return $decorated->getConfiguration($app);
    }
}
