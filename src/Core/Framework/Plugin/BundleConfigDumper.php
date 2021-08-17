<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - Remove this class as not needed anymore
 */
class BundleConfigDumper implements EventSubscriberInterface
{
    /**
     * @var BundleConfigGeneratorInterface
     */
    private $bundleConfigGenerator;

    /**
     * @var string
     */
    private $projectDir;

    public function __construct(
        BundleConfigGeneratorInterface $bundleConfigGenerator,
        string $projectDir
    ) {
        $this->bundleConfigGenerator = $bundleConfigGenerator;
        $this->projectDir = $projectDir;
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    public function dump(): void
    {
        $config = $this->bundleConfigGenerator->getConfig();

        file_put_contents(
            $this->projectDir . '/var/plugins.json',
            json_encode($config, \JSON_PRETTY_PRINT)
        );
    }
}
