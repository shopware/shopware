<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\NavigationLoadedEvent;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class NavigationLoadedEventTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var NavigationLoaderInterface
     */
    protected $loader;

    protected function setUp(): void
    {
        $this->loader = $this->getContainer()->get(NavigationLoader::class);
        parent::setUp();
    }

    public function testEventDispatched(): void
    {
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $this->addEventListener($dispatcher, NavigationLoadedEvent::class, $listener);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        $this->loader->load($navigationId, $context, $navigationId);
    }
}
