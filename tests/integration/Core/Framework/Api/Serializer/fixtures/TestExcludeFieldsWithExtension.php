<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Serializer\fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
class TestExcludeFieldsWithExtension extends SerializationFixture
{
    public function getInput(): EntityCollection|Entity
    {
        $extendable = new ArrayEntity([
            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'name' => 'main',
            'createdAt' => new \DateTime('2018-01-15T08:01:16.000+00:00'),
        ]);

        $collection = new EntityCollection([
            new ArrayEntity([
                'id' => '548faa1f7846436c85944f4aea792d96',
                'forbiddenFields' => 'should be excluded',
                'name' => 'toMany#1',
            ]),
            new ArrayEntity([
                'id' => '548faa1f7846436c85944f4aea792d97',
                'forbiddenFields' => 'should be excluded',
                'name' => 'toMany#2',
            ]),
        ]);

        $extendable->addExtension('toMany', $collection);
        $extendable->addExtension('toOne', new ArrayEntity([
            'id' => '548faa1f7846436c85944f4aea792d95',
            'forbiddenFields' => 'should be excluded',
            'name' => 'toOne',
        ]));

        return $extendable;
    }

    /**
     * @return array<string, mixed>
     */
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
                    'self' => \sprintf('%s/extendable/1d23c1b015bf43fb97e89008cf42d6fe', $baseUrl),
                ],
                'relationships' => [
                    'extensions' => [
                        'data' => [
                            'type' => 'extension',
                            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
                        ],
                    ],
                ],
                'meta' => [],
            ],
            'included' => [
                [
                    'id' => '548faa1f7846436c85944f4aea792d96',
                    'type' => 'extended',
                    'attributes' => [
                        'name' => 'toMany#1',
                        'createdAt' => null,
                        'updatedAt' => null,
                        'extendableId' => null,
                    ],
                    'links' => [
                        'self' => \sprintf('%s/extended/548faa1f7846436c85944f4aea792d96', $baseUrl),
                    ],
                    'relationships' => [
                        'toOne' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/extended/548faa1f7846436c85944f4aea792d96/to-one', $baseUrl),
                            ],
                        ],
                        'toMany' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/extended/548faa1f7846436c85944f4aea792d96/to-many', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => [],
                ],
                [
                    'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
                    'type' => 'extension',
                    'attributes' => [],
                    'links' => [],
                    'relationships' => [
                        'toMany' => [
                            'data' => [
                                [
                                    'type' => 'extended',
                                    'id' => '548faa1f7846436c85944f4aea792d96',
                                ],
                            ],
                            'links' => [
                                'related' => \sprintf('%s/extendable/1d23c1b015bf43fb97e89008cf42d6fe/extensions/toMany', $baseUrl),
                            ],
                        ],
                        'toOne' => [
                            'data' => null,
                            'links' => [
                                'related' => \sprintf('%s/extendable/1d23c1b015bf43fb97e89008cf42d6fe/extensions/toOne', $baseUrl),
                            ],
                        ],
                    ],
                    'meta' => [],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getJsonFixtures(): array
    {
        return [
            'extensions' => [
                'toMany' => [
                    [
                        'extensions' => [],
                        'id' => '548faa1f7846436c85944f4aea792d96',
                        'name' => 'toMany#1',
                        'apiAlias' => 'array',
                    ],
                    [
                        'extensions' => [],
                        'id' => '548faa1f7846436c85944f4aea792d97',
                        'name' => 'toMany#2',
                        'apiAlias' => 'array',
                    ],
                ],
                'toOne' => [
                    'extensions' => [],
                    'id' => '548faa1f7846436c85944f4aea792d95',
                    'name' => 'toOne',
                    'apiAlias' => 'array',
                ],
            ],
            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'name' => 'main',
            'createdAt' => '2018-01-15T08:01:16.000+00:00',
        ];
    }
}
