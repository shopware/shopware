/**
 * @sw-package framework
 */

import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import EntityFactory from 'src/core/data/entity-factory.data';
import Entity from 'src/core/data/entity.data';
import EntityCollection from 'src/core/data/entity-collection.data';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';

const changesetGenerator = new ChangesetGenerator();
const entityFactory = new EntityFactory();

describe('src/core/data/changeset-generator.data.js', () => {
    beforeAll(() => {
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });
    });

    it('should generate no changes', async () => {
        const testEntity = entityFactory.create('product_manufacturer');

        const { changes } = changesetGenerator.generate(testEntity);

        expect(changes).toBeNull();
    });

    [
        {
            description: 'Change property name',
            entityName: 'cms_page',
            originChanges: { name: 'Microsoft' },
            entityChanges: { name: 'Shopware AG' },
            expected: { name: 'Shopware AG' },
        },
        {
            description: 'Should create full changeset',
            entityName: 'cms_page',
            originChanges: { name: 'Microsoft' },
            entityChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['sum', 'add', 'divide'],
                },
            },
            expected: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['sum', 'add', 'divide'],
                },
            },
        },
        {
            description: 'should not return an changeset when origin and draft are identical',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['sum', 'add', 'divide'],
                },
            },
            entityChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['sum', 'add', 'divide'],
                },
            },
            expected: null,
        },
        {
            description:
                'should not return an changeset when origin and draft are identical except the key order in objects',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['sum', 'add', 'divide'],
                },
            },
            entityChanges: {
                config: {
                    test: ['sum', 'add', 'divide'],
                    b: 'bar',
                    a: 'foo',
                },
            },
            expected: null,
        },
        {
            description: 'Should create a changeset when the order in arrays are changing',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    numbers: [1, 2, 3],
                },
            },
            entityChanges: {
                config: {
                    numbers: [2, 1, 3],
                },
            },
            expected: {
                config: {
                    numbers: [2, 1, 3],
                },
            },
        },
        {
            description:
                'Should create a changeset when the order in arrays are changing. In combination with object key order changes.',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['First', 'Second', 'Third'],
                },
            },
            entityChanges: {
                config: {
                    test: ['Second', 'First', 'Third'],
                    b: 'bar',
                    a: 'foo',
                },
            },
            expected: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['Second', 'First', 'Third'],
                },
            },
        },
        {
            description: 'Should be able to null an array value',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    numbers: [1, 2, 3],
                },
            },
            entityChanges: {
                config: {
                    numbers: null,
                },
            },
            expected: {
                config: {
                    numbers: null,
                },
            },
        },
        {
            description: 'Should be able to null some scalar value',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware',
                        },
                        sum: 'mary',
                    },
                },
            },
            entityChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware',
                        },
                        sum: null,
                    },
                },
            },
            expected: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware',
                        },
                        sum: null,
                    },
                },
            },
        },
        {
            description: 'Should create a changeset the json field when a field was removed completely',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware',
                        },
                        animals: ['dog', 'cat', 'bird'],
                    },
                },
            },
            entityChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware',
                        },
                    },
                },
            },
            expected: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware',
                        },
                    },
                },
            },
        },
        {
            description: 'Should create a changeset when the json root is an object which is resetted to null',
            entityName: 'cms_page',
            originChanges: {
                config: {},
            },
            entityChanges: {
                config: null,
            },
            expected: {
                config: null,
            },
        },
        {
            description: 'Should create a changeset when the json root is an array which is resetted to null',
            entityName: 'cms_page',
            originChanges: {
                config: [],
            },
            entityChanges: {
                config: null,
            },
            expected: {
                config: null,
            },
        },
        {
            description: 'Should create a changeset when the json root is an array and the order changes',
            entityName: 'cms_page',
            originChanges: {
                config: [1, 2, 3],
            },
            entityChanges: {
                config: [2, 1, 3],
            },
            expected: {
                config: [2, 1, 3],
            },
        },
        {
            description: 'Should not create a changeset when the json root is an object and the order changes',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'a',
                    b: 'b',
                    c: 'c',
                },
            },
            entityChanges: {
                config: {
                    a: 'a',
                    c: 'c',
                    b: 'b',
                },
            },
            expected: null,
        },
    ].forEach(({ description, entityChanges, originChanges, expected, entityName }) => {
        it(`${description}`, async () => {
            const testEntity = entityFactory.create(entityName);

            Object.entries(originChanges).forEach(([key, value]) => {
                testEntity.getDraft()[key] = value;
                testEntity.getOrigin()[key] = value;
            });

            Object.entries(entityChanges).forEach(([key, value]) => {
                testEntity[key] = value;
            });

            const { changes } = changesetGenerator.generate(testEntity);

            if (changes !== null && changes.hasOwnProperty('id')) {
                delete changes.id;
            }
            if (changes !== null && changes.hasOwnProperty('id')) {
                delete changes.versionId;
            }

            expect(changes).toEqual(expected);
        });
    });

    describe('fields that allow an empty string', () => {
        beforeAll(() => {
            Shopware.EntityDefinition.add('empty_string_test', {
                entity: 'empty_string_test',
                properties: {
                    id: { type: 'uuid', flags: { primary_key: true, required: true } },
                    requiredName: { type: 'string', flags: { required: true, allow_empty_string: true } },
                    optionalNote: { type: 'string', flags: { allow_empty_string: true } },
                    plainName: { type: 'string', flags: { required: true } },
                },
            });
        });

        it('keeps an empty string on a required field that allows it', () => {
            const testEntity = entityFactory.create('empty_string_test');
            testEntity.getDraft().requiredName = 'Ada';
            testEntity.getOrigin().requiredName = 'Ada';
            testEntity.requiredName = '';

            const { changes } = changesetGenerator.generate(testEntity);

            expect(changes.requiredName).toBe('');
        });

        it('sends an empty string on a new entity', () => {
            const testEntity = entityFactory.create('empty_string_test');
            testEntity.requiredName = '';

            const { changes } = changesetGenerator.generate(testEntity);

            expect(changes.requiredName).toBe('');
        });

        it('still nulls an empty string on every other field', () => {
            const testEntity = entityFactory.create('empty_string_test');
            testEntity.getDraft().optionalNote = 'note';
            testEntity.getOrigin().optionalNote = 'note';
            testEntity.getDraft().plainName = 'Ada';
            testEntity.getOrigin().plainName = 'Ada';
            testEntity.optionalNote = '';
            testEntity.plainName = '';

            const { changes } = changesetGenerator.generate(testEntity);

            expect(changes.optionalNote).toBeNull();
            expect(changes.plainName).toBeNull();
        });
    });

    describe('to-many associations which were not loaded', () => {
        const parentId = 'parent-id';

        function createCollection(entities = []) {
            return new EntityCollection('/missing-association-child', 'missing_association_child', null, null, entities);
        }

        function createChild(id) {
            return new Entity(id, 'missing_association_child', { id, name: 'Child' });
        }

        beforeAll(() => {
            const toMany = {
                oneToMany: {
                    type: 'association',
                    relation: 'one_to_many',
                    entity: 'missing_association_child',
                    flags: { cascade_delete: true },
                    primary: 'id',
                    referenceField: 'parentId',
                },
                manyToMany: {
                    type: 'association',
                    relation: 'many_to_many',
                    entity: 'missing_association_child',
                    mapping: 'missing_association_parent_child',
                    local: 'parentId',
                    reference: 'childId',
                    flags: {},
                },
            };

            Shopware.EntityDefinition.add('missing_association_parent', {
                entity: 'missing_association_parent',
                properties: {
                    id: { type: 'uuid', flags: { primary_key: true, required: true } },
                    name: { type: 'string', flags: {} },
                    ...toMany,
                    oneToManyExtension: { ...toMany.oneToMany, flags: { extension: true, cascade_delete: true } },
                    manyToManyExtension: { ...toMany.manyToMany, flags: { extension: true } },
                },
            });

            Shopware.EntityDefinition.add('missing_association_child', {
                entity: 'missing_association_child',
                properties: {
                    id: { type: 'uuid', flags: { primary_key: true, required: true } },
                    name: { type: 'string', flags: {} },
                    children: { ...toMany.oneToMany },
                },
            });
        });

        it('generates the changeset of the loaded fields', () => {
            const parent = new Entity(parentId, 'missing_association_parent', {
                id: parentId,
                name: 'Ada',
                extensions: {},
            });
            parent.name = 'Grace';

            const { changes, deletionQueue } = changesetGenerator.generate(parent);

            expect(changes).toEqual({ id: parentId, name: 'Grace' });
            expect(deletionQueue).toEqual([]);
        });

        it('treats every entity of a collection added to an unloaded association as new', () => {
            const parent = new Entity(parentId, 'missing_association_parent', {
                id: parentId,
                extensions: {},
            });
            parent.oneToMany = createCollection([createChild('child-1')]);
            parent.manyToMany = createCollection([createChild('child-2')]);
            parent.extensions.oneToManyExtension = createCollection([createChild('child-3')]);
            parent.extensions.manyToManyExtension = createCollection([createChild('child-4')]);

            const { changes, deletionQueue } = changesetGenerator.generate(parent);

            expect(changes).toEqual({
                id: parentId,
                oneToMany: [{ id: 'child-1' }],
                manyToMany: [{ id: 'child-2' }],
                oneToManyExtension: [{ id: 'child-3' }],
                manyToManyExtension: [{ id: 'child-4' }],
            });
            expect(deletionQueue).toEqual([]);
        });

        it('does not delete loaded associated entities when the draft collection is missing', () => {
            const parent = new Entity(
                parentId,
                'missing_association_parent',
                { id: parentId, oneToMany: null, manyToMany: null, extensions: {} },
                {
                    originData: {
                        id: parentId,
                        oneToMany: createCollection([createChild('child-1')]),
                        manyToMany: createCollection([createChild('child-2')]),
                        extensions: {
                            oneToManyExtension: createCollection([createChild('child-3')]),
                            manyToManyExtension: createCollection([createChild('child-4')]),
                        },
                    },
                },
            );

            const { changes, deletionQueue } = changesetGenerator.generate(parent);

            expect(changes).toBeNull();
            expect(deletionQueue).toEqual([]);
        });

        it('generates the changeset of a nested entity whose own association was not loaded', () => {
            const child = createChild('child-1');
            const parent = new Entity(parentId, 'missing_association_parent', {
                id: parentId,
                oneToMany: createCollection([child]),
                extensions: {},
            });
            child.name = 'Changed';

            const { changes, deletionQueue } = changesetGenerator.generate(parent);

            expect(changes).toEqual({
                id: parentId,
                oneToMany: [{ id: 'child-1', name: 'Changed' }],
            });
            expect(deletionQueue).toEqual([]);
        });
    });
});
