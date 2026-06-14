<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Validation\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class ValidationControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @param array<string, string> $emailPayload
     */
    #[DataProvider('emailPayloadProvider')]
    public function testValidateEmailAddress(array $emailPayload, int $expectedStatusCode): void
    {
        $browser = $this->getBrowser();

        $browser->request('POST', '/api/_action/validation/email', $emailPayload, server: ['CONTENT_TYPE' => 'application/json']);

        $result = $browser->getResponse()->getStatusCode();

        static::assertSame($expectedStatusCode, $result);
    }

    /**
     * @return iterable<string, array<string, int|array<string, string>>>
     */
    public static function emailPayloadProvider(): iterable
    {
        yield 'valid email' => [
            'emailPayload' => ['email' => 'valid@email.com'],
            'expectedStatusCode' => 204,
        ];
        yield 'invalid email' => [
            'emailPayload' => ['email' => 'invalid@email'],
            'expectedStatusCode' => 422,
        ];
        yield 'no payload' => [
            'emailPayload' => [],
            'expectedStatusCode' => 400,
        ];
    }
}
