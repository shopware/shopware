<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @package core
 *
 * @internal
 *
 * @deprecated tag:v6.5.0 - reason:remove-subscriber - Remove this class as not needed anymore
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

    /**
     * @internal
     */
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        $config = $this->bundleConfigGenerator->getConfig();

        file_put_contents(
            $this->projectDir . '/var/plugins.json',
            json_encode($config, \JSON_PRETTY_PRINT)
        );
    }
}
