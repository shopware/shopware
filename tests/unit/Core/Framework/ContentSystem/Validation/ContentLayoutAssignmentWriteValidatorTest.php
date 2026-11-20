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
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
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

    #[TestDox('adds no violation when the bound layout is not loadable (null root source), deferring to the FK')]
    public function testNullRootSourceAddsNoViolation(): void
    {
        $event = $this->validate(rootSource: null, assignmentType: 'category');

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('adds no violation when the layout root source matches the assignment type')]
    public function testMatchingRootSourceAddsNoViolation(): void
    {
        $event = $this->validate(rootSource: 'category', assignmentType: 'category');

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('adds a rootSourceAssignmentMismatch violation when the root source differs from the assignment type')]
    public function testMismatchingRootSourceAddsViolation(): void
    {
        $event = $this->validate(rootSource: 'product', assignmentType: 'category');

        $collected = $event->getExceptions()->getExceptions();
        static::assertCount(1, $collected);
        static::assertInstanceOf(WriteConstraintViolationException::class, $collected[0]);

        $violations = $collected[0]->getViolations();
        static::assertCount(1, $violations);
        static::assertSame(ContentSystemException::ROOT_SOURCE_ASSIGNMENT_MISMATCH, $violations->get(0)->getCode());
    }

    #[TestDox('skips a command whose entity is not an assignable definition without reading the root source')]
    public function testSkipsCommandForNonAssignableDefinition(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));

        $reader = static::createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('read');

        $event = $this->event([$this->assignmentCommand()]);
        (new ContentLayoutAssignmentWriteValidator($registry, $reader))->preValidate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    #[TestDox('skips an assignable command that does not carry the content_layout_id field without reading the root source')]
    public function testSkipsAssignmentCommandWithoutContentLayoutId(): void
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn('category');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);

        $reader = static::createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('read');

        $event = $this->event([$this->assignmentCommand(hasContentLayoutId: false)]);
        (new ContentLayoutAssignmentWriteValidator($registry, $reader))->preValidate($event);

        static::assertSame([], $event->getExceptions()->getExceptions());
    }

    private function validate(?string $rootSource, string $assignmentType): PreWriteValidationEvent
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn($assignmentType);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn($definition);

        $reader = static::createStub(LayoutRootSourceReader::class);
        $reader->method('read')->willReturn($rootSource);

        $event = $this->event([$this->assignmentCommand()]);
        (new ContentLayoutAssignmentWriteValidator($registry, $reader))->preValidate($event);

        return $event;
    }

    private function assignmentCommand(bool $hasContentLayoutId = true): WriteCommand
    {
        $command = static::createStub(WriteCommand::class);
        $command->method('getEntityName')->willReturn('category_content_layout');
        $command->method('hasField')->willReturnCallback(
            static fn (string $field): bool => $hasContentLayoutId && $field === 'content_layout_id'
        );
        $command->method('getPayload')->willReturn(['content_layout_id' => $this->ids->get('layout')]);
        $command->method('getPath')->willReturn('/0');

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
