<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @phpstan-import-type CommandConfig from PostWriteValidationEventTest
 *
 * @internal
 */
#[CoversClass(PreWriteValidationEvent::class)]
class PreWriteValidationEventTest extends TestCase
{
    private WriteContext $context;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [ProductDefinition::class, CategoryDefinition::class, ProductTranslationDefinition::class, OrderDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    /**
     * @param CommandConfig[] $commands
     * @param array<string, array<array<string, string>>> $assertions
     */
    #[DataProvider('getPrimaryKeysProvider')]
    public function testGetPrimaryKeys(array $commands, array $assertions): void
    {
        $commands = $this->getCommands($commands);

        $event = new PreWriteValidationEvent($this->context, $commands);

        foreach ($assertions as $entity => $ids) {
            static::assertEquals($ids, $event->getPrimaryKeys($entity), \sprintf('Primary keys for entity %s not match', $entity));
        }
    }

    /**
     * @param CommandConfig[] $commands
     * @param array<string, array<array<string, string>>> $assertions
     */
    #[DataProvider('getPrimaryKeysProvider')]
    public function testGetDeletedPrimaryKeysProvider(array $commands, array $assertions): void
    {
        $commands = $this->getCommands($commands);

        $event = new PreWriteValidationEvent($this->context, $commands);

        foreach ($assertions as $entity => $ids) {
            static::assertEquals($ids, $event->getPrimaryKeys($entity), \sprintf('Primary keys for entity %s not match', $entity));
        }
    }

    /**
     * @param CommandConfig[] $commands
     * @param array<string, array<array<string, string>>> $assertions
     */
    #[DataProvider('getDeletedPrimaryKeysProvider')]
    public function testGetDeletedPrimaryKeys(array $commands, array $assertions): void
    {
        $commands = $this->getCommands($commands);

        $event = new PreWriteValidationEvent($this->context, $commands);

        foreach ($assertions as $entity => $ids) {
            static::assertEquals($ids, $event->getDeletedPrimaryKeys($entity), \sprintf('Deleted primary keys for entity %s not match', $entity));
        }
    }

    public static function getPrimaryKeysProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test single insert' => [
            'commands' => [
                [
                    'entityName' => 'product',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
            ],
            'assertions' => [
                'product' => [['id' => $ids->getBytes('p1')]],
            ],
        ];

        yield 'Test multi insert' => [
            'commands' => [
                [
                    'entityName' => 'product',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
                [
                    'entityName' => 'product',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p2')],
                ],
                [
                    'entityName' => 'product',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p3')],
                ],
                [
                    'entityName' => 'category',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => 'category',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => 'category',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'product_translation',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => 'product_translation',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => 'product_translation',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'order',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('f1')],
                ],
                [
                    'entityName' => 'order',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('f2')],
                ],
                [
                    'entityName' => 'order',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('f3')],
                ],
            ],
            'assertions' => [
                'product' => [
                    ['id' => $ids->getBytes('p1')],
                    ['id' => $ids->getBytes('p2')],
                    ['id' => $ids->getBytes('p3')],
                ],
                'order' => [
                    ['id' => $ids->getBytes('f1')],
                    ['id' => $ids->getBytes('f2')],
                    ['id' => $ids->getBytes('f3')],
                ],
                'category' => [
                    ['id' => $ids->getBytes('c1')],
                    ['id' => $ids->getBytes('c2')],
                    ['id' => $ids->getBytes('c3')],
                ],
                'product_translation' => [
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c1')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c2')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c3')],
                ],
                'not-found' => [],
            ],
        ];
    }

    public static function getDeletedPrimaryKeysProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test single delete' => [
            'commands' => [
                [
                    'entityName' => 'product',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
            ],
            [
                'product' => [['id' => $ids->getBytes('p1')]],
            ],
        ];

        yield 'Test multi insert' => [
            'commands' => [
                [
                    'entityName' => 'product',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('p1')],
                ],
                [
                    'entityName' => 'product',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('p2')],
                ],
                [
                    'entityName' => 'product',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('p3')],
                ],
                [
                    'entityName' => 'category',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => 'category',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => 'category',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'product_translation',
                    'type' => 'delete',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c1')],
                ],
                [
                    'entityName' => 'product_translation',
                    'type' => 'insert',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c2')],
                ],
                [
                    'entityName' => 'product_translation',
                    'type' => 'delete',
                    'primaryKey' => ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c3')],
                ],
                [
                    'entityName' => 'order',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('f1')],
                ],
                [
                    'entityName' => 'order',
                    'type' => 'delete',
                    'primaryKey' => ['id' => $ids->getBytes('f2')],
                ],
                [
                    'entityName' => 'order',
                    'type' => 'insert',
                    'primaryKey' => ['id' => $ids->getBytes('f3')],
                ],
            ],
            'assertions' => [
                'product' => [
                    ['id' => $ids->getBytes('p2')],
                    ['id' => $ids->getBytes('p3')],
                ],
                'category' => [
                    ['id' => $ids->getBytes('c2')],
                    ['id' => $ids->getBytes('c3')],
                ],
                'product_translation' => [
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c1')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'product_id' => $ids->getBytes('c3')],
                ],
                'order' => [
                    ['id' => $ids->getBytes('f2')],
                ],
                'not-found' => [],
            ],
        ];
    }

    /**
     * @param CommandConfig[] $commandsArray
     *
     * @return list<WriteCommand>
     */
    private function getCommands(array $commandsArray): array
    {
        $commands = [];

        foreach ($commandsArray as $command) {
            $definition = $this->definitionInstanceRegistry->getByEntityName($command['entityName']);
            $existence = new EntityExistence('', [], false, false, false, []);
            $primaryKey = $command['primaryKey'];

            switch ($command['type']) {
                case 'insert':
                    $commands[] = new InsertCommand($definition, [], $primaryKey, $existence, '');
                    break;
                case 'delete':
                    $commands[] = new DeleteCommand($definition, $primaryKey, $existence);
                    break;
            }
        }

        return $commands;
    }
}
