<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Message\UpdateServiceMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UpdateServiceMessage::class)]
class UpdateServiceMessageTest extends TestCase
{
    public function testMeta(): void
    {
        $message = new UpdateServiceMessage('MyCoolService');

        static::assertSame('MyCoolService', $message->name);
    }
}
