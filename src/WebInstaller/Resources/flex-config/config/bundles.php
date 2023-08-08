<?php
declare(strict_types=1);

$bundles = [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true],
    Shopware\Core\Framework\Framework::class => ['all' => true],
    Shopware\Core\System\System::class => ['all' => true],
    Shopware\Core\Content\Content::class => ['all' => true],
    Shopware\Core\Checkout\Checkout::class => ['all' => true],
    Shopware\Core\Maintenance\Maintenance::class => ['all' => true],
    Shopware\Core\DevOps\DevOps::class => ['e2e' => true],
    Shopware\Core\Profiling\Profiling::class => ['all' => true],
    Shopware\Administration\Administration::class => ['all' => true],
    Shopware\Elasticsearch\Elasticsearch::class => ['all' => true],
    Shopware\Storefront\Storefront::class => ['all' => true],
];

if (class_exists(Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class)) {
    $bundles[Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class] = ['all' => true];
}

if (class_exists(Enqueue\Bundle\EnqueueBundle::class)) {
    $bundles[Enqueue\Bundle\EnqueueBundle::class] = ['all' => true];
}

if (class_exists(Enqueue\MessengerAdapter\Bundle\EnqueueAdapterBundle::class)) {
    $bundles[Enqueue\MessengerAdapter\Bundle\EnqueueAdapterBundle::class] = ['all' => true];
}

return $bundles;
