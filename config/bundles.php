<?php declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Enqueue\Bundle\EnqueueBundle::class => ['all' => true],
    Enqueue\MessengerAdapter\Bundle\EnqueueAdapterBundle::class => ['all' => true],
    Shopware\Core\Framework\Framework::class => ['all' => true],
    Shopware\Core\System\System::class => ['all' => true],
    Shopware\Core\Content\Content::class => ['all' => true],
    Shopware\Core\Checkout\Checkout::class => ['all' => true],
    Shopware\Core\Profiling\Profiling::class => ['dev' => true],
    Shopware\Core\DevOps\DevOps::class => ['all' => true],
    Shopware\Administration\Administration::class => ['all' => true],
    Shopware\Docs\Docs::class => ['all' => true],
    Shopware\Storefront\Storefront::class => ['all' => true],
    Shopware\Elasticsearch\Elasticsearch::class => ['all' => true],
];
