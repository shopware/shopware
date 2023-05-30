<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer\fixtures;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;

/**
 * @internal
 */
class TestBasicWithToManyExtension extends SerializationFixture
{
    public function getInput(): EntityCollection|Entity
    {
        $extendable = new ArrayEntity([
            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'createdAt' => new \DateTime('2018-01-15T08:01:16.000+00:00'),
        ]);

        $collection = new EntityCollection([
            new ArrayEntity([
                'id' => '548faa1f7846436c85944f4aea792d96',
                'name' => 'toMany#1',
            ]),
        ]);

        $extendable->addExtension('toMany', $collection);

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
                        'createdAt' => null,
                        'updatedAt' => null,
                        'extendableId' => null,
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
                                'related' => sprintf('%s/extendable/1d23c1b015bf43fb97e89008cf42d6fe/extensions/toMany', $baseUrl),
                            ],
                        ],
                        'toOne' => [
                            'data' => null,
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

    /**
     * @return array<string, mixed>
     */
    protected function getJsonFixtures(): array
    {
        return [
            'id' => '1d23c1b015bf43fb97e89008cf42d6fe',
            'createdAt' => '2018-01-15T08:01:16.000+00:00',
            '_uniqueIdentifier' => null,
            'versionId' => null,
            'translated' => [],
            'updatedAt' => null,
            'extensions' => [
                'toMany' => [
                    [
                        '_uniqueIdentifier' => null,
                        'versionId' => null,
                        'translated' => [],
                        'createdAt' => null,
                        'updatedAt' => null,
                        'extensions' => [],
                        'id' => '548faa1f7846436c85944f4aea792d96',
                        'name' => 'toMany#1',
                    ],
                ],
            ],
        ];
    }
}
