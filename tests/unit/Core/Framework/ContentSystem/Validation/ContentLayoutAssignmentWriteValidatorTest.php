<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutAssignmentWriteValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcome;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutAssignmentWriteValidator::class)]
class ContentLayoutAssignmentWriteValidatorTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('adds no violation when the bound layout is not loadable (no root source), deferring to the FK')]
    public function testUnloadableLayoutAddsNoViolation(): void
    {
        $event = $this->validate([], [$this->bind('/0', 'layout')]);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('adds no violation when the layout root source matches the assignment type')]
    public function testMatchingRootSourceAddsNoViolation(): void
    {
        $event = $this->validate(['layout' => 'category'], [$this->bind('/0', 'layout')]);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('adds a rootSourceAssignmentMismatch violation at the assignment path when the root source differs from the assignment type')]
    public function testMismatchingRootSourceAddsViolation(): void
    {
        $event = $this->validate(['layout' => 'product'], [$this->bind('/0', 'layout')]);

        $collected = $event->getExceptions()->getExceptions();
        static::assertCount(1, $collected);
        static::assertInstanceOf(WriteConstraintViolationException::class, $collected[0]);
        static::assertSame('/0', $collected[0]->getPath());

        $violations = $collected[0]->getViolations();
        static::assertCount(1, $violations);
        static::assertSame(ContentSystemException::ROOT_SOURCE_ASSIGNMENT_MISMATCH, $violations->get(0)->getCode());
    }

    #[TestDox('judges an assignment the batch binds twice on the layout it binds last')]
    public function testAssignmentBoundTwiceIsJudgedOnItsLastBinding(): void
    {
        $event = $this->validate(
            ['product-layout' => 'product', 'category-layout' => 'category'],
            [$this->bind('/0', 'product-layout'), $this->bind('/1', 'category-layout', UpdateCommand::class)],
        );

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('skips a command whose entity is not an assignable definition without reading the root source')]
    public function testSkipsCommandForNonAssignableDefinition(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));

        $reader = $this->createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('rootSources');

        $event = $this->event([$this->bind('/0', 'layout')]);
        $this->validator($registry, $reader)->preValidate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('skips an assignment update that does not set the content_layout_id field without reading the root source')]
    public function testSkipsAssignmentUpdateWithoutContentLayoutId(): void
    {
        $reader = $this->createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('rootSources');

        $event = $this->event([$this->command(UpdateCommand::class, '/0', ['sales_channel_id' => 'channel'])]);
        $this->validator($this->assignableRegistry(), $reader)->preValidate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    /**
     * @param array<string, string> $rootSourceByLayoutKey
     * @param list<WriteCommand> $commands
     */
    private function validate(array $rootSourceByLayoutKey, array $commands): PreWriteValidationEvent
    {
        $rootSources = [];
        foreach ($rootSourceByLayoutKey as $key => $rootSource) {
            $rootSources[$this->ids->get($key)] = $rootSource;
        }

        $reader = static::createStub(LayoutRootSourceReader::class);
        $reader->method('rootSources')->willReturn($rootSources);

        $event = $this->event($commands);
        $this->validator($this->assignableRegistry(), $reader)->preValidate($event);

        return $event;
    }

    private function validator(DefinitionInstanceRegistry $registry, LayoutRootSourceReader $reader): ContentLayoutAssignmentWriteValidator
    {
        return new ContentLayoutAssignmentWriteValidator($registry, $reader, new WriteOutcome(static::createStub(RowLoader::class)));
    }

    private function assignableRegistry(): DefinitionInstanceRegistry
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn('category');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);

        return $registry;
    }

    /**
     * @param class-string<InsertCommand|UpdateCommand> $commandClass
     */
    private function bind(string $path, string $layoutKey, string $commandClass = InsertCommand::class): WriteCommand
    {
        return $this->command($commandClass, $path, ['content_layout_id' => $this->ids->get($layoutKey)]);
    }

    /**
     * @param class-string<InsertCommand|UpdateCommand> $commandClass
     * @param array<string, string> $payload
     */
    private function command(string $commandClass, string $path, array $payload): WriteCommand
    {
        $command = static::createStub($commandClass);
        $command->method('getEntityName')->willReturn('category_content_layout');
        $command->method('getPrimaryKey')->willReturn(['id' => 'assignment-1']);
        $command->method('getPayload')->willReturn($payload);
        $command->method('getPath')->willReturn($path);
        $command->method('hasAnyField')->willReturnCallback(
            static fn (string ...$storageNames): bool => array_intersect($storageNames, array_keys($payload)) !== []
        );

        return $command;
    }

    /**
     * @param list<WriteCommand> $commands
     */
    private function event(array $commands): PreWriteValidationEvent
    {
        return new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), $commands);
    }
}
