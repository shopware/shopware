<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Controller\SystemHealthController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Message\RunSystemChecks;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemHealthController::class)]
class SystemHealthControllerTest extends TestCase
{
    private CollectingMessageBus $messageBus;

    private SystemHealthController $controller;

    protected function setUp(): void
    {
        $storedResult = ['status' => 'OK', 'healthy' => true, 'message' => 'APP_URL points to this shop', 'extra' => [], 'checkedAt' => '2026-11-24T10:00:00+00:00'];

        $storage = static::createStub(SystemCheckResultStorage::class);
        $storage->method('all')->willReturn(['AppUrlVerification' => $storedResult]);

        $this->messageBus = new CollectingMessageBus();
        $this->controller = new SystemHealthController(
            [$this->check('AppUrlVerification', Category::EXTERNAL), $this->check('LocalesReadiness', Category::FEATURE)],
            $storage,
            $this->messageBus,
        );
    }

    public function testListsEveryCheckWithItsLastResult(): void
    {
        $response = $this->controller->list();

        static::assertSame([
            'checks' => [
                [
                    'name' => 'AppUrlVerification',
                    'category' => 'EXTERNAL',
                    'result' => ['status' => 'OK', 'healthy' => true, 'message' => 'APP_URL points to this shop', 'extra' => [], 'checkedAt' => '2026-11-24T10:00:00+00:00'],
                ],
                ['name' => 'LocalesReadiness', 'category' => 'FEATURE', 'result' => null],
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    public function testQueuesAllChecks(): void
    {
        $response = $this->controller->run(new Request());

        static::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
        static::assertEquals([new RunSystemChecks()], $this->messages());
    }

    public function testQueuesOneCheck(): void
    {
        $this->controller->run(new Request(['check' => 'LocalesReadiness']));

        static::assertEquals([new RunSystemChecks('LocalesReadiness')], $this->messages());
    }

    public function testRejectsAnUnknownCheck(): void
    {
        $this->expectExceptionObject(RoutingException::invalidRequestParameter('check'));

        $this->controller->run(new Request(['check' => 'Unknown']));
    }

    /**
     * @return list<object>
     */
    private function messages(): array
    {
        return array_values(array_map(static fn ($envelope) => $envelope->getMessage(), $this->messageBus->getMessages()));
    }

    private function check(string $name, Category $category): BaseCheck
    {
        $check = static::createStub(BaseCheck::class);
        $check->method('name')->willReturn($name);
        $check->method('category')->willReturn($category);

        return $check;
    }
}
