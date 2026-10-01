<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\NewsletterRecipientProvider;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<NewsletterRecipientProvider>
 */
#[Package('after-sales')]
#[CoversClass(NewsletterRecipientProvider::class)]
class NewsletterRecipientProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): NewsletterRecipientProvider {
        return new NewsletterRecipientProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return NewsletterRecipientDefinition::ENTITY_NAME;
    }
}
