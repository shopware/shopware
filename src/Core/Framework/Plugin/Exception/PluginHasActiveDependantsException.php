<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginHasActiveDependantsException extends PluginException
{
    /**
     * @param list<PluginEntity> $dependants
     */
    public function __construct(
        string $dependency,
        array $dependants
    ) {
        $dependantNameList = array_map(static fn ($plugin) => \sprintf('"%s"', $plugin->getName()), $dependants);

        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'FRAMEWORK__PLUGIN_HAS_DEPENDANTS',
            'The following plugins depend on "{{ dependency }}": {{ dependantNames }}. They need to be deactivated before "{{ dependency }}" can be deactivated or uninstalled itself.',
            [
                'dependency' => $dependency,
                'dependants' => $dependants,
                'dependantNames' => implode(', ', $dependantNameList),
            ]
        );
    }
}
