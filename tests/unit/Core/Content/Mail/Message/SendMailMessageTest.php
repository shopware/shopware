<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Message\SendMailMessage;

/**
 * @internal
 */
#[CoversClass(SendMailMessage::class)]
class SendMailMessageTest extends TestCase
{
    public function testCreateMessage(): void
    {
        $message = new SendMailMessage('mail-data/test');

        static::assertSame('mail-data/test', $message->getMailDataPath());
    }
}
