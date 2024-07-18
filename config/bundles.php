<?php declare(strict_types=1);

use Composer\InstalledVersions;

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Shopware\Core\Profiling\Profiling::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Shopware\Core\Framework\Framework::class => ['all' => true],
    Shopware\Core\System\System::class => ['all' => true],
    Shopware\Core\Content\Content::class => ['all' => true],
    Shopware\Core\Checkout\Checkout::class => ['all' => true],
    Shopware\Core\DevOps\DevOps::class => ['all' => true],
    Shopware\Core\Maintenance\Maintenance::class => ['all' => true],
    Shopware\Administration\Administration::class => ['all' => true],
    Shopware\Storefront\Storefront::class => ['all' => true],
    Shopware\Elasticsearch\Elasticsearch::class => ['all' => true],
];

if (InstalledVersions::isInstalled('symfony/web-profiler-bundle')) {
    $bundles[Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class] = ['dev' => true, 'test' => true, 'phpstan_dev' => true];
}
return $bundles;
