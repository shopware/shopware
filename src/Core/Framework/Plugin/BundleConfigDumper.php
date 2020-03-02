<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BundleConfigDumper implements EventSubscriberInterface
{
    /**
     * @var BundleConfigGeneratorInterface
     */
    private $bundleConfigGenerator;

    /**
     * @var string
     */
    private $cacheDir;

    public function __construct(
        BundleConfigGeneratorInterface $bundleConfigGenerator,
        string $cacheDir
    ) {
        $this->bundleConfigGenerator = $bundleConfigGenerator;
        $this->cacheDir = $cacheDir;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => 'dump',
            PluginPostDeactivateEvent::class => 'dump',
        ];
    }

    public function dump(): void
    {
        $config = $this->bundleConfigGenerator->getConfig();

        file_put_contents(
            $this->cacheDir . '/../../plugins.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );
    }
}
