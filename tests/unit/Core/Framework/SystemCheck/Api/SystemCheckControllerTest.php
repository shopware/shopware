<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Api\SystemCheckController;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\StoredResult;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Shopware\Core\Framework\SystemCheck\SystemCheckResultStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheckController::class)]
class SystemCheckControllerTest extends TestCase
{
    private SystemChecker&MockObject $systemChecker;

    private SystemCheckController $controller;

    protected function setUp(): void
    {
        $storedResult = new StoredResult(
            new Result('AppUrlVerification', Status::OK, 'APP_URL points to this shop', true),
            new \DateTimeImmutable('2026-11-24 10:00:00', new \DateTimeZone('UTC')),
        );

        $storage = static::createStub(SystemCheckResultStorage::class);
        $storage->method('all')->willReturn(['AppUrlVerification' => $storedResult]);

        $this->systemChecker = $this->createMock(SystemChecker::class);
        $this->controller = new SystemCheckController(
            [$this->check('AppUrlVerification', Category::EXTERNAL, ['apps']), $this->check('LocalesReadiness', Category::FEATURE)],
            $storage,
            $this->systemChecker,
        );
    }

    public function testListsEveryCheckWithItsLastResult(): void
    {
        $this->systemChecker->expects($this->never())->method('check');

        $response = $this->controller->list();

        static::assertSame([
            'checks' => [
                [
                    'name' => 'AppUrlVerification',
                    'category' => 'EXTERNAL',
                    'tags' => ['apps'],
                    'result' => ['status' => 'OK', 'healthy' => true, 'message' => 'APP_URL points to this shop', 'extra' => [], 'checkedAt' => '2026-11-24T10:00:00+00:00'],
                ],
                ['name' => 'LocalesReadiness', 'category' => 'FEATURE', 'tags' => [], 'result' => null],
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    public function testQueuesAllChecks(): void
    {
        $this->systemChecker->expects($this->once())->method('queueCheck')->with(null);

        $response = $this->controller->run(new Request());

        static::assertSame(Response::HTTP_ACCEPTED, $response->getStatusCode());
    }

    public function testQueuesOneCheck(): void
    {
        $this->systemChecker->expects($this->once())->method('queueCheck')->with('LocalesReadiness');

        $this->controller->run(new Request(['check' => 'LocalesReadiness']));
    }

    /**
     * @param list<string> $tags
     */
    private function check(string $name, Category $category, array $tags = []): BaseCheck
    {
        $check = static::createStub(BaseCheck::class);
        $check->method('name')->willReturn($name);
        $check->method('category')->willReturn($category);
        $check->method('tags')->willReturn($tags);

        return $check;
    }
}
