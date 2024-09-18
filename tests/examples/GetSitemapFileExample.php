<?php declare(strict_types=1);

namespace Shopware\Tests\Examples;

use Shopware\Core\Content\Sitemap\Extension\SitemapFileExtension;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

readonly class GetSitemapFileExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sitemap.get-file.pre' => 'replace',
        ];
    }

    public function replace(SitemapFileExtension $event): void
    {
        $event->result = new Response('Hello World!');

        $event->stopPropagation();
    }
}
