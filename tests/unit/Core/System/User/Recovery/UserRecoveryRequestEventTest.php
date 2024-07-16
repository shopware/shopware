<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Recovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\ScalarValuesStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(UserRecoveryRequestEvent::class)]
class UserRecoveryRequestEventTest extends TestCase
{
    public function testScalarValuesCorrectly(): void
    {
        $event = new UserRecoveryRequestEvent(
            new UserRecoveryEntity(),
            'my-reset-url',
            Context::createDefaultContext(),
        );

        $storer = new ScalarValuesStorer();
        $stored = $storer->store($event, []);

        $flow = new StorableFlow('foo', Context::createDefaultContext(), $stored);

        $storer->restore($flow);

        static::assertArrayHasKey('resetUrl', $flow->data());
        static::assertEquals('my-reset-url', $flow->data()['resetUrl']);
    }
}
