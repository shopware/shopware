<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Validation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\Validation\ContentLayoutDefaultValidator;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutDefaultValidator::class)]
class ContentLayoutDefaultValidatorTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('accepts a default product layout whose root source is product')]
    public function testAcceptsDefaultLayoutWithMatchingRootSource(): void
    {
        $this->createValidator(rootSource: 'product')->validateDefaultChange(
            new BeforeSystemConfigChangedEvent(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('layout'), null)
        );

        $this->expectNotToPerformAssertions();
    }

    #[TestDox('rejects a default category layout whose root source is product')]
    public function testRejectsDefaultLayoutWithMismatchingRootSource(): void
    {
        $this->expectExceptionObject(ContentSystemException::rootSourceAssignmentMismatch('product', 'category'));

        $this->createValidator(rootSource: 'product')->validateDefaultChange(
            new BeforeSystemConfigChangedEvent(CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('layout'), null)
        );
    }

    #[TestDox('rejects a default layout id that names no existing layout')]
    public function testRejectsDefaultLayoutThatDoesNotExist(): void
    {
        $this->expectExceptionObject(ContentSystemException::contentLayoutNotFound($this->ids->get('layout')));

        $this->createValidator(rootSource: null)->validateDefaultChange(
            new BeforeSystemConfigChangedEvent(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, $this->ids->get('layout'), null)
        );
    }

    #[TestDox('accepts unsetting the default layout without reading any layout')]
    public function testAcceptsUnsettingTheDefaultLayout(): void
    {
        $reader = static::createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('read');

        (new ContentLayoutDefaultValidator($this->createDefinitionRegistry(), $reader, static::createStub(Connection::class)))->validateDefaultChange(
            new BeforeSystemConfigChangedEvent(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, null, null)
        );
    }

    #[TestDox('accepts an empty string as unsetting the default layout')]
    public function testAcceptsEmptyStringAsUnsettingTheDefaultLayout(): void
    {
        $reader = static::createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('read');

        (new ContentLayoutDefaultValidator($this->createDefinitionRegistry(), $reader, static::createStub(Connection::class)))->validateDefaultChange(
            new BeforeSystemConfigChangedEvent(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, '', null)
        );
    }

    #[TestDox('rejects a default layout value that is not a string')]
    public function testRejectsNonStringDefaultLayoutValue(): void
    {
        $this->expectExceptionObject(ContentSystemException::contentLayoutNotFound('{"id":"layout"}'));

        $this->createValidator(rootSource: 'product')->validateDefaultChange(
            new BeforeSystemConfigChangedEvent(ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT, ['id' => 'layout'], null)
        );
    }

    #[TestDox('ignores system config keys that are not a default layout key')]
    public function testIgnoresUnrelatedConfigKeys(): void
    {
        $reader = static::createMock(LayoutRootSourceReader::class);
        $reader->expects($this->never())->method('read');

        (new ContentLayoutDefaultValidator($this->createDefinitionRegistry(), $reader, static::createStub(Connection::class)))->validateDefaultChange(
            new BeforeSystemConfigChangedEvent('core.cms.default_product_cms_page', 'not-a-layout', null)
        );
    }

    #[TestDox('blocks deleting a layout that is configured as a default')]
    public function testBlocksDeletingADefaultLayout(): void
    {
        $this->expectExceptionObject(ContentSystemException::defaultContentLayoutDeletion([$this->ids->get('layout')]));

        $this->createValidator(configuredDefaults: [$this->ids->get('layout')])->validateDeletion(
            $this->deleteEvent($this->ids->get('layout'))
        );
    }

    #[TestDox('allows deleting a layout that is not configured as a default')]
    public function testAllowsDeletingANonDefaultLayout(): void
    {
        $this->createValidator(configuredDefaults: [$this->ids->get('other-layout')])->validateDeletion(
            $this->deleteEvent($this->ids->get('layout'))
        );

        $this->expectNotToPerformAssertions();
    }

    /**
     * @param list<string> $configuredDefaults
     */
    private function createValidator(?string $rootSource = null, array $configuredDefaults = []): ContentLayoutDefaultValidator
    {
        $reader = static::createStub(LayoutRootSourceReader::class);
        $reader->method('read')->willReturn($rootSource);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(array_map(
            static fn (string $layoutId) => json_encode(['_value' => $layoutId], \JSON_THROW_ON_ERROR),
            $configuredDefaults,
        ));

        return new ContentLayoutDefaultValidator($this->createDefinitionRegistry(), $reader, $connection);
    }

    private function createDefinitionRegistry(): DefinitionInstanceRegistry
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            new ProductContentLayoutDefinition(),
            new CategoryContentLayoutDefinition(),
            new LandingPageContentLayoutDefinition(),
            new ContentLayoutDefinition(),
        ]);

        return $registry;
    }

    private function deleteEvent(string $layoutId): EntityDeleteEvent
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [new ContentLayoutDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $command = new DeleteCommand(
            $registry->getByEntityName(ContentLayoutDefinition::ENTITY_NAME),
            ['id' => Uuid::fromHexToBytes($layoutId)],
            new EntityExistence(ContentLayoutDefinition::ENTITY_NAME, ['id' => $layoutId], true, true, true, []),
        );

        return EntityDeleteEvent::create(WriteContext::createFromContext(Context::createDefaultContext()), [$command]);
    }
}
