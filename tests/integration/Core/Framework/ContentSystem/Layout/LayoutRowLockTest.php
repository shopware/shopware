<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Layout;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Layout\LayoutRowLock;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\TestElementTypeLoader;

/**
 * @internal
 */
#[Package('framework')]
class LayoutRowLockTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private LayoutRowLock $rowLock;

    private Context $context;

    protected function setUp(): void
    {
        $this->rowLock = static::getContainer()->get(LayoutRowLock::class);
        $this->context = Context::createDefaultContext();
    }

    #[TestDox('a layout id without a row is not found and the operation never runs')]
    public function testUnknownLayoutIsNotFound(): void
    {
        $layoutId = Uuid::randomHex();

        $this->expectExceptionObject(ContentSystemException::contentLayoutNotFound($layoutId));

        $this->rowLock->run($layoutId, static fn () => static::fail('The operation must not run for an unknown layout.'));
    }

    #[TestDox('returns what the operation returns')]
    public function testReturnsTheOperationResult(): void
    {
        $layoutId = $this->createLayout('before');

        static::assertSame('result', $this->rowLock->run($layoutId, static fn (): string => 'result'));
    }

    #[TestDox('a failing operation rolls back what it wrote')]
    public function testOperationFailureRollsBack(): void
    {
        $layoutId = $this->createLayout('before');
        $failure = new \RuntimeException('operation failed');

        $this->expectExceptionObject($failure);

        try {
            $this->rowLock->run($layoutId, function () use ($layoutId, $failure): void {
                $this->repository()->update([['id' => $layoutId, 'name' => 'after']], $this->context);

                throw $failure;
            });
        } finally {
            $layout = $this->repository()->search(new Criteria([$layoutId]), $this->context)->getEntities()->first();
            static::assertInstanceOf(ContentLayoutEntity::class, $layout);
            static::assertSame('before', $layout->getName());
        }
    }

    private function createLayout(string $name): string
    {
        $id = Uuid::randomHex();

        $this->repository()->create([[
            'id' => $id,
            'name' => $name,
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [['id' => 'element', 'component' => TestElementTypeLoader::RESOLVABLE, 'properties' => []]],
        ]], $this->context);

        return $id;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        return static::getContainer()->get('content_layout.repository');
    }
}
