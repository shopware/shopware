<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\IdsCollection;

class PostWriteValidationEventTest extends TestCase
{
    /**
     * @dataProvider getPrimaryKeysProvider
     */
    public function testGetPrimaryKeys(PostWriteValidationEvent $event, array $assertions): void
    {
        foreach ($assertions as $entity => $ids) {
            static::assertEquals($ids, $event->getPrimaryKeys($entity), \sprintf('Primary keys for entity %s not match', $entity));
        }
    }

    /**
     * @dataProvider getPrimaryKeysProvider
     */
    public function testGetDeletedPrimaryKeysProvider(PostWriteValidationEvent $event, array $assertions): void
    {
        foreach ($assertions as $entity => $ids) {
            static::assertEquals($ids, $event->getPrimaryKeys($entity), \sprintf('Primary keys for entity %s not match', $entity));
        }
    }

    /**
     * @dataProvider getDeletedPrimaryKeysProvider
     */
    public function testGetDeletedPrimaryKeys(PostWriteValidationEvent $event, array $assertions): void
    {
        foreach ($assertions as $entity => $ids) {
            static::assertEquals($ids, $event->getDeletedPrimaryKeys($entity), \sprintf('Deleted primary keys for entity %s not match', $entity));
        }
    }

    public function getDeletedPrimaryKeysProvider(): \Generator
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        $definition = $this->createMock(EntityDefinition::class);

        $ids = new IdsCollection();

        yield 'Test single delete' => [
            new PostWriteValidationEvent($context, [$this->delete('product', ['id' => $ids->get('p1')])]),
            [
                'product' => [['id' => $ids->get('p1')]],
            ],
        ];

        yield 'Test multi insert' => [
            new PostWriteValidationEvent($context, [
                $this->insert('product', ['id' => $ids->get('p1')]),
                $this->delete('product', ['id' => $ids->get('p2')]),
                $this->delete('product', ['id' => $ids->get('p3')]),

                $this->insert('category', ['id' => $ids->get('c1')]),
                $this->delete('category', ['id' => $ids->get('c2')]),
                $this->delete('category', ['id' => $ids->get('c3')]),

                $this->delete('translation', ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c1')]),
                $this->insert('translation', ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c2')]),
                $this->delete('translation', ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c3')]),

                $this->insert('foo', ['id' => $ids->get('f1')]),
                $this->delete('foo', ['id' => $ids->get('f2')]),
                $this->insert('foo', ['id' => $ids->get('f3')]),
            ]),
            [
                'product' => [
                    ['id' => $ids->get('p2')],
                    ['id' => $ids->get('p3')],
                ],
                'category' => [
                    ['id' => $ids->get('c2')],
                    ['id' => $ids->get('c3')],
                ],
                'translation' => [
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c1')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c3')],
                ],
                'foo' => [
                    ['id' => $ids->get('f2')],
                ],
                'not-found' => [],
            ],
        ];
    }

    public function getPrimaryKeysProvider(): \Generator
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        $ids = new IdsCollection();

        yield 'Test single insert' => [
            new PostWriteValidationEvent($context, [$this->insert('product', ['id' => $ids->get('p1')])]),
            [
                'product' => [['id' => $ids->get('p1')]],
            ],
        ];
        yield 'Test multi insert' => [
            new PostWriteValidationEvent($context, [
                $this->insert('product', ['id' => $ids->get('p1')]),
                $this->insert('product', ['id' => $ids->get('p2')]),
                $this->insert('product', ['id' => $ids->get('p3')]),

                $this->insert('category', ['id' => $ids->get('c1')]),
                $this->insert('category', ['id' => $ids->get('c2')]),
                $this->insert('category', ['id' => $ids->get('c3')]),

                $this->insert('translation', ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c1')]),
                $this->insert('translation', ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c2')]),
                $this->insert('translation', ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c3')]),

                $this->delete('foo', ['id' => $ids->get('f1')]),
                $this->delete('foo', ['id' => $ids->get('f2')]),
                $this->delete('foo', ['id' => $ids->get('f3')]),
            ]),
            [
                'product' => [
                    ['id' => $ids->get('p1')],
                    ['id' => $ids->get('p2')],
                    ['id' => $ids->get('p3')],
                ],
                'foo' => [
                    ['id' => $ids->get('f1')],
                    ['id' => $ids->get('f2')],
                    ['id' => $ids->get('f3')],
                ],
                'category' => [
                    ['id' => $ids->get('c1')],
                    ['id' => $ids->get('c2')],
                    ['id' => $ids->get('c3')],
                ],
                'translation' => [
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c1')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c2')],
                    ['language_id' => Defaults::LANGUAGE_SYSTEM, 'id' => $ids->get('c3')],
                ],
                'not-found' => [],
            ],
        ];
    }

    private function insert(string $entity, array $primaryKey): InsertCommand
    {
        $definition = $this->createMock(EntityDefinition::class);
        $definition->expects(static::any())
            ->method('getEntityName')
            ->willReturn($entity);

        $existence = new EntityExistence('', [], false, false, false, []);

        return new InsertCommand($definition, [], $primaryKey, $existence, '');
    }

    private function delete(string $entity, array $primaryKey): DeleteCommand
    {
        $definition = $this->createMock(EntityDefinition::class);
        $definition->expects(static::any())
            ->method('getEntityName')
            ->willReturn($entity);

        return new DeleteCommand($definition, $primaryKey, new EntityExistence('', [], false, false, false, []));
    }
}
