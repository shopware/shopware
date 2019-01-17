<?php declare(strict_types=1);

namespace Shopware\Administration\Controller;

use Shopware\Core\Framework\Exception\MissingParameterException;
use Shopware\Core\Framework\Plugin\Exception\PluginConfigNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException;
use Shopware\Core\Framework\Plugin\Helper\PluginConfigReader;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PluginConfigurationController extends AbstractController
{
    private const PLUGIN_NAME_PARAMETER = 'plugin_name';

    /**
     * @var PluginConfigReader
     */
    private $configReader;

    /**
     * @var Kernel
     */
    private $kernel;

    public function __construct(PluginConfigReader $configReader, Kernel $kernel)
    {
        $this->configReader = $configReader;
        $this->kernel = $kernel;
    }

    /**
     * @Route("/api/v{version}/_action/core/plugin-config", name="api.action.core.plugin-config", methods={"GET"})
     *
     * @throws MissingParameterException
     * @throws PluginNotFoundException
     * @throws PluginConfigNotFoundException
     */
    public function getConfiguration(Request $request): JsonResponse
    {
        $pluginName = $request->query->get(self::PLUGIN_NAME_PARAMETER);

        if (!$pluginName) {
            throw new MissingParameterException(self::PLUGIN_NAME_PARAMETER);
        }

        $activePlugins = $this->kernel::getPlugins()->getActives();

        if (!\array_key_exists($pluginName, $activePlugins)) {
            throw new PluginNotFoundException($pluginName);
        }

        $plugin = $activePlugins[$pluginName];
        $path = $plugin->getPath() . '/Resources/config.xml';

        if (!is_file($path)) {
            throw new PluginConfigNotFoundException($pluginName);
        }

        $config = $this->configReader->read($path);

        return new JsonResponse($config);
    }
}
