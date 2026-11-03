<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\App\Subscriber\DiscardUnconfirmedAppSecretsListener;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DiscardUnconfirmedAppSecretsListener::class)]
class DiscardUnconfirmedAppSecretsListenerTest extends TestCase
{
    public function testDiscardsUnconfirmedSecretsOfEveryPendingApp(): void
    {
        $appRepository = StaticEntityRepository::of(AppCollection::class, [['app-one', 'app-two']]);

        $listener = new DiscardUnconfirmedAppSecretsListener($appRepository);
        $listener(new ShopIdDeletedEvent());

        static::assertSame(
            [[
                ['id' => 'app-one', 'unconfirmedAppSecrets' => null],
                ['id' => 'app-two', 'unconfirmedAppSecrets' => null],
            ]],
            $appRepository->updates
        );
    }
}
