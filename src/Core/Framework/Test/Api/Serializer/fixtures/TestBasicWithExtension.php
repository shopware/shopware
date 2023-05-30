<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;
use function sprintf;

/**
 * @internal
 */
class TestBasicWithExtension extends SerializationFixture
{
    public function getInput(): EntityCollection|Entity
    {
        $extendable = new ArrayEntity([
            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'createdAt' => new \DateTime('2018-01-15T08:01:16.000+00:00'),
        ]);

        $extendable->addExtension('toOne', new ArrayEntity([
            'id' => '6f51622eb3814c75ae0263cece27ce72',
            'name' => 'toOne',
        ]));

        $extendable->addExtension('toOneWithoutApiAware', new ArrayEntity([
            'id' => '6f51622eb3814c75ae0263cece27ce72',
            'name' => 'toOneWithoutApiAware',
        ]));

        $collection = new EntityCollection([
            new ArrayEntity([
                'id' => '548faa1f7846436c85944f4aea792d96',
                'name' => 'toMany#1',
            ]),
            new ArrayEntity([
                'id' => '3e352be2d85846dd97529c0f6b544870',
                'name' => 'toMany#2',
            ]),
        ]);

        $extendable->addExtension('toMany', $collection);

        $extendable->addExtension('test', new ArrayEntity([
            'test' => 'testValue',
        ]));

        return $extendable;
    }

    protected function getJsonApiFixtures(string $baseUrl): array
    {
        return [
            'data' => [
                'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
                'type' => 'extendable',
                'attributes' => [
                    'createdAt' => '2018-01-15T08:01:16.000+00:00',
                    'updatedAt' => null,
                ],
                'links' => [
                    'self' => sprintf('%s/extendable/1d23c1b015bf43fb97e89008cf42d6fe', $baseUrl),
                ],
                'relationships' => [
                    'extensions' => [
                        'data' => [
                            'type' => 'extension',
                            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
                        ],
                    ],
                ],
                'meta' => null,
            ],
            'included' => [
                [
                    'id' => '548faa1f7846436c85944f4aea792d96',
                    'type' => 'extended',
                    'attributes' => [
                        'name' => 'toMany#1',
                        'extendableId' => null,
                        'createdAt' => null,
                        'updatedAt' => null,
                    ],
                    'links' => [
                        'self' => sprintf('%s/extended/548faa1f7846436c85944f4aea792d96', $baseUrl),
                    ],
                    'relationships' => [
                        'toOne' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/extended/548faa1f7846436c85944f4aea792d96/to-one', $baseUrl),
                            ],
                        ],
                        'toMany' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/extended/548faa1f7846436c85944f4aea792d96/to-many', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ],
                [
                    'id' => '3e352be2d85846dd97529c0f6b544870',
                    'type' => 'extended',
                    'attributes' => [
                        'name' => 'toMany#2',
                        'extendableId' => null,
                        'createdAt' => null,
                        'updatedAt' => null,
                    ],
                    'links' => [
                        'self' => sprintf('%s/extended/3e352be2d85846dd97529c0f6b544870', $baseUrl),
                    ],
                    'relationships' => [
                        'toOne' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/extended/3e352be2d85846dd97529c0f6b544870/to-one', $baseUrl),
                            ],
                        ],
                        'toMany' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/extended/3e352be2d85846dd97529c0f6b544870/to-many', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ],
                [
                    'id' => '6f51622eb3814c75ae0263cece27ce72',
                    'type' => 'extended',
                    'attributes' => [
                        'name' => 'toOne',
                        'extendableId' => null,
                        'createdAt' => null,
                        'updatedAt' => null,
                    ],
                    'links' => [
                        'self' => sprintf('%s/extended/6f51622eb3814c75ae0263cece27ce72', $baseUrl),
                    ],
                    'relationships' => [
                        'toOne' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/extended/6f51622eb3814c75ae0263cece27ce72/to-one', $baseUrl),
                            ],
                        ],
                        'toMany' => [
                            'data' => null,
                            'links' => [
                                'related' => sprintf('%s/extended/6f51622eb3814c75ae0263cece27ce72/to-many', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ],
                [
                    'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
                    'type' => 'extension',
                    'attributes' => [
                        'test' => [
                            'extensions' => [],
                            '_uniqueIdentifier' => null,
                            'translated' => [],
                            'test' => 'testValue',
                        ],
                    ],
                    'links' => [],
                    'relationships' => [
                        'toMany' => [
                            'data' => [
                                [
                                    'type' => 'extended',
                                    'id' => '548faa1f7846436c85944f4aea792d96',
                                ],
                                [
                                    'type' => 'extended',
                                    'id' => '3e352be2d85846dd97529c0f6b544870',
                                ],
                            ],
                            'links' => [
                                'related' => sprintf('%s/extendable/1d23c1b015bf43fb97e89008cf42d6fe/extensions/toMany', $baseUrl),
                            ],
                        ],
                        'toOne' => [
                            'data' => [
                                'type' => 'extended',
                                'id' => '6f51622eb3814c75ae0263cece27ce72',
                            ],
                            'links' => [
                                'related' => sprintf('%s/extendable/1d23c1b015bf43fb97e89008cf42d6fe/extensions/toOne', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => null,
                ],
            ],
        ];
    }

    protected function getJsonFixtures(): array
    {
        return [
            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'createdAt' => '2018-01-15T08:01:16.000+00:00',
            '_uniqueIdentifier' => null,
            'translated' => [],
            'extensions' => [
                'toOne' => [
                    '_uniqueIdentifier' => null,
                    'translated' => [],
                    'extensions' => [],
                    'id' => '6f51622eb3814c75ae0263cece27ce72',
                    'name' => 'toOne',
                ],
                'toMany' => [
                    [
                        '_uniqueIdentifier' => null,
                        'translated' => [],
                        'extensions' => [],
                        'id' => '548faa1f7846436c85944f4aea792d96',
                        'name' => 'toMany#1',
                    ],
                    [
                        '_uniqueIdentifier' => null,
                        'translated' => [],
                        'extensions' => [],
                        'id' => '3e352be2d85846dd97529c0f6b544870',
                        'name' => 'toMany#2',
                    ],
                ],
                'test' => [
                    '_uniqueIdentifier' => null,
                    'translated' => [],
                    'extensions' => [],
                    'test' => 'testValue',
                ],
            ],
        ];
    }
}
