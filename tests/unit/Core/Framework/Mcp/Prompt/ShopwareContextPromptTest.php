<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Prompt\ShopwareContextPrompt;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareContextPrompt::class)]
class ShopwareContextPromptTest extends TestCase
{
    public function testInvokeReturnsMessagesWithRoleAndContent(): void
    {
        $prompt = new ShopwareContextPrompt();
        $result = ($prompt)();

        static::assertIsArray($result);
        static::assertNotEmpty($result);
        static::assertArrayHasKey('role', $result[0]);
        static::assertArrayHasKey('content', $result[0]);
        static::assertSame('user', $result[0]['role']);
    }

    public function testContentContainsKeyPhrases(): void
    {
        $prompt = new ShopwareContextPrompt();
        $result = ($prompt)();

        $content = $result[0]['content'];
        static::assertStringContainsString('Shopware', $content);
        static::assertStringContainsString('entity', $content);
    }
}
