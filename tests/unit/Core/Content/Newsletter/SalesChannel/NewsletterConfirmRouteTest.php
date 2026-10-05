<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Newsletter\Extension\NewsletterConfirmRouteExtension;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterConfirmRoute::class)]
class NewsletterConfirmRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $dataBag = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('newsletter-confirm-route.confirm.pre', static function (NewsletterConfirmRouteExtension $extension) use ($dataBag, $context, $response): void {
            static::assertSame(['dataBag' => $dataBag, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new NewsletterConfirmRoute(
            static::createStub(EntityRepository::class),
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ClockInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->confirmWithResponse($dataBag, $context));
    }
}
