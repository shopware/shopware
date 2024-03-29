<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteResultFactory;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\EmptyEntityExistence;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(EntityWriteResultFactory::class)]
class EntityWriteResultFactoryTest extends TestCase
{
    /**
     * @param array<WriteCommand> $commands
     * @param array<string, array<string, array<string, mixed>>> $expected
     */
    #[DataProvider('buildResultProvider')]
    public function testBuildResult(array $commands, array $expected): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CountryDefinition::class, TaxDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $factory = new EntityWriteResultFactory(
            $registry,
            $this->createMock(Connection::class)
        );

        $queue = new WriteCommandQueue();

        // add all commands to queue, use the identifier system of DAL
        foreach ($commands as $command) {
            $identifier = WriteCommandQueue::hashedPrimary($registry, $command);
            $queue->add($command->getEntityName(), $identifier, $command);
        }

        $result = $factory->build($queue);

        // loop over expected written entity names
        foreach ($expected as $entity => $records) {
            static::assertArrayHasKey($entity, $result, 'Expected write results for entity ' . $entity);

            static::assertCount(\count($records), $result[$entity], 'Expected write results for entity ' . $entity);

            // now loop over the written records and compare the payloads
            foreach ($result[$entity] as $written) {
                $id = $written->getPrimaryKey();

                static::assertIsString($id, 'Expected write result to have a primary key as string in this test');

                static::assertArrayHasKey($id, $records, sprintf('Primary key %s was not expected to be written', $id));

                static::assertEquals($records[$id], $written->getPayload(), 'Expected payload to be equal');
            }
        }
    }

    public static function buildResultProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Test single definition, single command' => [
            [
                // fake class to reduce constructor complexity
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('country-1'), 'active' => false],
                    ['id' => $ids->getBytes('country-1')],
                ),
            ],
            [
                'country' => [
                    $ids->get('country-1') => ['id' => $ids->get('country-1'), 'active' => false],
                ],
            ],
        ];

        yield 'Test single definition, multiple commands' => [
            [
                // fake class to reduce constructor complexity
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('country-1'), 'active' => false],
                    ['id' => $ids->getBytes('country-1')],
                ),
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('country-2'), 'active' => true],
                    ['id' => $ids->getBytes('country-2')],
                ),
            ],
            [
                'country' => [
                    $ids->get('country-1') => ['id' => $ids->get('country-1'), 'active' => false],
                    $ids->get('country-2') => ['id' => $ids->get('country-2'), 'active' => true],
                ],
            ],
        ];

        yield 'Test multiple definitions, multiple commands' => [
            [
                // fake class to reduce constructor complexity
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('country-1'), 'active' => false],
                    ['id' => $ids->getBytes('country-1')],
                ),
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('country-2'), 'active' => true],
                    ['id' => $ids->getBytes('country-2')],
                ),
                // fake class to reduce constructor complexity
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('tax-1'), 'tax_rate' => 10],
                    ['id' => $ids->getBytes('tax-1')],
                    new TaxDefinition()
                ),
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('tax-2'), 'tax_rate' => 11],
                    ['id' => $ids->getBytes('tax-2')],
                    new TaxDefinition()
                ),
            ],
            [
                'country' => [
                    $ids->get('country-1') => ['id' => $ids->get('country-1'), 'active' => false],
                    $ids->get('country-2') => ['id' => $ids->get('country-2'), 'active' => true],
                ],
                'tax' => [
                    $ids->get('tax-1') => ['id' => $ids->get('tax-1'), 'taxRate' => 10],
                    $ids->get('tax-2') => ['id' => $ids->get('tax-2'), 'taxRate' => 11],
                ],
            ],
        ];

        yield 'Test merge payload for same definition and same command primary key' => [
            [
                // fake class to reduce constructor complexity
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('country-1'), 'active' => false],
                    ['id' => $ids->getBytes('country-1')],
                ),
                // fake class to reduce constructor complexity
                new UpdateCommandStub(
                    ['id' => $ids->getBytes('country-1'), 'position' => 10],
                    ['id' => $ids->getBytes('country-1')],
                ),
            ],
            [
                'country' => [
                    $ids->get('country-1') => [
                        'id' => $ids->get('country-1'),
                        'active' => false,
                        'position' => 10,
                    ],
                ],
            ],
        ];
    }
}

/**
 * @internal
 */
class UpdateCommandStub extends UpdateCommand
{
    public function __construct(array $payload, array $primaryKey, ?EntityDefinition $definition = null)
    {
        $definition = $definition ?? new CountryDefinition();

        parent::__construct(
            definition: $definition,
            payload: $payload,
            primaryKey: $primaryKey,
            existence: new EmptyEntityExistence(),
            path: '/' . Uuid::randomHex()
        );
    }
}
