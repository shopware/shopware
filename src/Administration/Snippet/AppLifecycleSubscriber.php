<?php

declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
readonly class AppLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private AppAdministrationSnippetPersister $appAdministrationSnippetPersister,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'onAppUpdate',
            AppUpdatedEvent::class => 'onAppUpdate',
        ];
    }

    public function onAppUpdate(AppInstalledEvent|AppUpdatedEvent $event): void
    {
        $app = $event->getApp();
        $snippets = $this->getSnippets($app);
        $this->appAdministrationSnippetPersister->updateSnippets($app, $snippets, $event->getContext());
    }

    /**
     * @return array<string, string>
     */
    private function getSnippets(AppEntity $app): array
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources/app/administration/snippet')) {
            return [];
        }

        $snippets = [];
        foreach ($fs->findFiles('*.json', 'Resources/app/administration/snippet') as $file) {
            $snippets[$file->getFilenameWithoutExtension()] = $file->getContents();
        }

        return $snippets;
    }
}
