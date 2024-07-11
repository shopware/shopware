<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\Message\UpdateServiceMessage;

/**
 * @internal
 */
#[CoversClass(UpdateServiceMessage::class)]
class UpdateServiceMessageTest extends TestCase
{
    public function testMeta(): void
    {
        $message = new UpdateServiceMessage('MyCoolService');

        static::assertSame('MyCoolService', $message->name);
    }
}
