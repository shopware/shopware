import ChangesetGenerator from 'src/core/data/changeset-generator.data';
import EntityFactory from 'src/core/data/entity-factory.data';
import entitySchemaMock from 'src/../test/_mocks_/entity-schema.json';
import Vue from 'vue';

const changesetGenerator = new ChangesetGenerator();
const entityFactory = new EntityFactory();

describe('src/core/data/changeset-generator.data.js', () => {
    beforeAll(() => {
        Shopware.Application.view = {
            setReactive: Vue.set
        };
        Object.entries(entitySchemaMock).forEach(([entityName, entityDefinition]) => {
            Shopware.EntityDefinition.add(entityName, entityDefinition);
        });
    });

    it('should generate no changes', async () => {
        const testEntity = entityFactory.create('product_manufacturer');

        const { changes } = changesetGenerator.generate(testEntity);

        expect(changes).toBe(null);
    });

    [
        {
            description: 'Change property name',
            entityName: 'cms_page',
            originChanges: { name: 'Microsoft' },
            entityChanges: { name: 'Shopware AG' },
            expected: { name: 'Shopware AG' }
        },
        {
            description: 'Should create full changeset',
            entityName: 'cms_page',
            originChanges: { name: 'Microsoft' },
            entityChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide'
                    ]
                }
            },
            expected: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide'
                    ]
                }
            }
        },
        {
            description: 'Shouldn´t return an changeset when origin and draft are identical',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide'
                    ]
                }
            },
            entityChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide'
                    ]
                }
            },
            expected: null
        },
        {
            description: 'Shouldn´t return an changeset when origin and draft are identical except the key order in objects',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'sum',
                        'add',
                        'divide'
                    ]
                }
            },
            entityChanges: {
                config: {
                    test: [
                        'sum',
                        'add',
                        'divide'
                    ],
                    b: 'bar',
                    a: 'foo'
                }
            },
            expected: null
        },
        {
            description: 'Should create a changeset when the order in arrays are changing',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    numbers: [1, 2, 3]
                }
            },
            entityChanges: {
                config: {
                    numbers: [2, 1, 3]
                }
            },
            expected: {
                config: {
                    numbers: [2, 1, 3]
                }
            }
        },
        {
            // eslint-disable-next-line max-len
            description: 'Should create a changeset when the order in arrays are chaning. In combination with object key order changes.',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: [
                        'First',
                        'Second',
                        'Third'
                    ]
                }
            },
            entityChanges: {
                config: {
                    test: [
                        'Second',
                        'First',
                        'Third'
                    ],
                    b: 'bar',
                    a: 'foo'
                }
            },
            expected: {
                config: {
                    a: 'foo',
                    b: 'bar',
                    test: ['Second', 'First', 'Third']
                }
            }
        },
        {
            description: 'Should be able to null an array value',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    numbers: [1, 2, 3]
                }
            },
            entityChanges: {
                config: {
                    numbers: null
                }
            },
            expected: {
                config: {
                    numbers: null
                }
            }
        },
        {
            description: 'Should be able to null some scalar value',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware'
                        },
                        sum: 'mary'
                    }
                }
            },
            entityChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware'
                        },
                        sum: null
                    }
                }
            },
            expected: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware'
                        },
                        sum: null
                    }
                }
            }
        },
        {
            description: 'Should create a changeset the json field when a field was removed completely',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware'
                        },
                        animals: [
                            'dog',
                            'cat',
                            'bird'
                        ]
                    }
                }
            },
            entityChanges: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware'
                        }
                    }
                }
            },
            expected: {
                config: {
                    test: {
                        foo: {
                            bar: 'Shop',
                            second: 'ware'
                        }
                    }
                }
            }
        },
        {
            description: 'Should create a changeset when the json root is an object which is resetted to null',
            entityName: 'cms_page',
            originChanges: {
                config: {}
            },
            entityChanges: {
                config: null
            },
            expected: {
                config: null
            }
        },
        {
            description: 'Should create a changeset when the json root is an array which is resetted to null',
            entityName: 'cms_page',
            originChanges: {
                config: []
            },
            entityChanges: {
                config: null
            },
            expected: {
                config: null
            }
        },
        {
            description: 'Should create a changeset when the json root is an array and the order changes',
            entityName: 'cms_page',
            originChanges: {
                config: [1, 2, 3]
            },
            entityChanges: {
                config: [2, 1, 3]
            },
            expected: {
                config: [2, 1, 3]
            }
        },
        {
            description: 'Should not create a changeset when the json root is an object and the order changes',
            entityName: 'cms_page',
            originChanges: {
                config: {
                    a: 'a',
                    b: 'b',
                    c: 'c'
                }
            },
            entityChanges: {
                config: {
                    a: 'a',
                    c: 'c',
                    b: 'b'
                }
            },
            expected: null
        }
    ].forEach(({ description, entityChanges, originChanges, expected, entityName }) => {
        it(description || 'should create the correct changeset', async () => {
            const testEntity = entityFactory.create(entityName);

            Object.entries(originChanges).forEach(([key, value]) => {
                Vue.set(testEntity.getDraft(), key, value);
                Vue.set(testEntity.getOrigin(), key, value);
            });

            Object.entries(entityChanges).forEach(([key, value]) => {
                Vue.set(testEntity, key, value);
            });

            const { changes } = changesetGenerator.generate(testEntity);

            expect(changes).toEqual(expected);
        });
    });
});
