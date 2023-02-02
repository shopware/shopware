<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\ShopwareHttpException;

class PluginHasActiveDependantsException extends ShopwareHttpException
{
    /**
     * @param PluginEntity[] $dependants
     */
    public function __construct(string $dependency, array $dependants)
    {
        $dependantNameList = array_map(static function ($plugin) {
            return sprintf('"%s"', $plugin->getName());
        }, $dependants);

        parent::__construct(
            'The following plugins depend on "{{ dependency }}": {{ dependantNames }}. They need to be deactivated before "{{ dependency }}" can be deactivated or uninstalled itself.',
            [
                'dependency' => $dependency,
                'dependants' => $dependants,
                'dependantNames' => implode(', ', $dependantNameList),
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_HAS_DEPENDANTS';
    }
}
