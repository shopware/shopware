<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutResolvabilityValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class ContentLayoutWriteValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the well-formedness gate rejects a content layout with an unregistered component on write')]
    public function testRejectsUnregisteredComponent(): void
    {
        $context = Context::createDefaultContext();

        try {
            $this->repository()->create([$this->layout('Sw:Test:DefinitelyUnregistered')], $context);
            static::fail('Expected the well-formedness gate to reject the unregistered component.');
        } catch (WriteException $exception) {
            static::assertStringContainsString('Sw:Test:DefinitelyUnregistered', $exception->getMessage());
            static::assertStringContainsString('is not a registered element type', $exception->getMessage());
        }
    }

    #[TestDox('the well-formedness gate persists an incomplete but well-formed layout')]
    public function testAcceptsIncompleteButWellFormedLayout(): void
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $this->repository()->create([$this->layout($this->registeredComponent(), $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    #[TestDox('the write-context skip flag bypasses the gate for trusted bulk writes')]
    public function testSkipFlagBypassesGate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(LayoutResolvabilityValidator::SKIP_VALIDATION_STATE);
        $id = Uuid::randomHex();

        $this->repository()->create([$this->layout('Sw:Test:DefinitelyUnregistered', $id)], $context);

        static::assertSame($id, $this->repository()->searchIds(new Criteria([$id]), $context)->firstId());
    }

    /**
     * @return array<string, mixed>
     */
    private function layout(string $component, ?string $id = null): array
    {
        return [
            'id' => $id ?? Uuid::randomHex(),
            'name' => 'gate-test',
            'version' => '1.0.0',
            'layout' => [
                ['id' => Uuid::randomHex(), 'component' => $component, 'properties' => []],
            ],
        ];
    }

    private function registeredComponent(): string
    {
        $types = $this->getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $name = array_key_first($types);
        static::assertIsString($name);

        return $name;
    }

    /**
     * @return EntityRepository<ContentLayoutCollection>
     */
    private function repository(): EntityRepository
    {
        $repository = $this->getContainer()->get('content_layout.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
