<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\SyncController;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SyncController::class)]
class SyncControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testInvalidWriteInputExceptionIsConvertedToBadRequest(): void
    {
        $client = $this->getBrowser();

        /** @var non-empty-string $payload */
        $payload = json_encode([
            [
                'action' => 'delete',
                'entity' => 'product',
                'payload' => [
                    'test' => true,
                ],
            ],
        ]);

        $client->request('POST', '/api/_action/sync', content: $payload);

        /** @var string $response */
        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        static::assertEquals(Response::HTTP_BAD_REQUEST, $response['errors'][0]['status']);
        static::assertEquals('Invalid payload. Should contain a list of associative arrays', $response['errors'][0]['detail']);
    }
}
