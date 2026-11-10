<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Payment\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AbstractResponse::class)]
class AbstractResponseTest extends TestCase
{
    public function testErrorMessageIsNullByDefault(): void
    {
        $response = $this->createResponse();

        static::assertNull($response->getErrorMessage());
    }

    public function testCreateAssignsData(): void
    {
        $response = $this->createResponse()::create([
            'message' => 'test message',
        ]);

        static::assertSame('test message', $response->getErrorMessage());
    }

    private function createResponse(): AbstractResponse
    {
        return new class extends AbstractResponse {
        };
    }
}
