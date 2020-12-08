<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Faker\Generator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldGenerator implements DemodataGeneratorInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $attributeSetRepository;

    /**
     * @var Connection
     */
    private $connection;

    private $attributeSets = [];

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionRegistry;

    public function __construct(EntityRepositoryInterface $attributeSetRepository, Connection $connection, DefinitionInstanceRegistry $definitionRegistry)
    {
        $this->attributeSetRepository = $attributeSetRepository;
        $this->connection = $connection;
        $this->definitionRegistry = $definitionRegistry;
    }

    public function getDefinition(): string
    {
        return CustomFieldSetDefinition::class;
    }

    public function getRandomSet(): ?array
    {
        return $this->attributeSets ? $this->attributeSets[array_rand($this->attributeSets)] : null;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $console = $context->getConsole();
        $console->comment('Generate attribute sets: ' . $numberOfItems);
        $console->progressStart($numberOfItems);

        for ($i = 0; $i < $numberOfItems; ++$i) {
            $this->generateCustomFieldSet($options, $context);

            $console->progressAdvance(1);
        }
        $console->progressFinish();

        $relations = $options['relations'];
        $sum = array_sum($relations);
        if ($sum <= 0) {
            return;
        }

        $console->comment('Set attributes for entities: ' . $sum);
        $console->progressStart($sum);
        foreach ($relations as $relation => $count) {
            if (!$count || $count < 1) {
                continue;
            }

            $console->comment('\nSet attributes for ' . $count . ' ' . $relation . ' entities');

            $rndSet = $this->getRandomSet();
            $this->generateCustomFields($relation, $count, $rndSet['attributes'], $context);

            $console->progressAdvance($count);
        }
        $console->progressFinish();
    }

    private function randomCustomField(string $prefix, DemodataContext $context): array
    {
        $types = [
            CustomFieldTypes::INT,
            CustomFieldTypes::FLOAT,
            CustomFieldTypes::DATETIME,
            CustomFieldTypes::BOOL,
            CustomFieldTypes::TEXT,
        ];

        $name = $context->getFaker()->unique()->words(3, true);
        $type = $types[array_rand($types)];

        switch ($type) {
            case CustomFieldTypes::INT:
                $config = [
                    'componentName' => 'sw-field',
                    'type' => 'number',
                    'numberType' => 'int',
                    'customFieldType' => 'number',
                    'label' => [
                        'en-GB' => $name,
                    ],
                    'placeholder' => [
                        'en-GB' => 'Type a number...',
                    ],
                    'customFieldPosition' => 1,
                ];

                break;
            case CustomFieldTypes::FLOAT:
                $config = [
                    'componentName' => 'sw-field',
                    'type' => 'number',
                    'numberType' => 'float',
                    'customFieldType' => 'number',
                    'label' => [
                        'en-GB' => $name,
                    ],
                    'placeholder' => [
                        'en-GB' => 'Type a floating point number...',
                    ],
                    'customFieldPosition' => 1,
                ];

                break;
            case CustomFieldTypes::DATETIME:
                $config = [
                    'componentName' => 'sw-field',
                    'type' => 'date',
                    'dateType' => 'datetime',
                    'customFieldType' => 'date',
                    'label' => [
                        'en-GB' => $name,
                    ],
                    'customFieldPosition' => 1,
                ];

                break;
            case CustomFieldTypes::BOOL:
                $config = [
                    'componentName' => 'sw-field',
                    'type' => 'checkbox',
                    'customFieldType' => 'checkbox',
                    'label' => [
                        'en-GB' => $name,
                    ],
                    'customFieldPosition' => 1,
                ];

                break;
            default:
                $config = [
                    'componentName' => 'sw-field',
                    'type' => 'text',
                    'customFieldType' => 'text',
                    'label' => [
                        'en-GB' => $name,
                    ],
                    'placeholder' => [
                        'en-GB' => 'Type a text...',
                    ],
                    'customFieldPosition' => 1,
                ];

                break;
        }

        return [
            'id' => Uuid::randomHex(),
            'name' => mb_strtolower($prefix) . '_' . str_replace(' ', '_', $name),
            'type' => $type,
            'config' => $config,
        ];
    }

    private function generateCustomFieldSet(array $options, DemodataContext $context): void
    {
        $relationNames = array_keys($options['relations']);
        $relations = array_map(function ($rel) {
            return ['id' => Uuid::randomHex(), 'entityName' => $rel];
        }, $relationNames);

        $attributeCount = random_int(1, 5);
        $attributes = [];

        $setName = $context->getFaker()->unique()->category;
        $prefix = 'custom_';

        for ($j = 0; $j < $attributeCount; ++$j) {
            $attributes[] = $this->randomCustomField($prefix . $setName, $context);
        }

        $set = [
            'id' => Uuid::randomHex(),
            'name' => $prefix . $setName,
            'config' => [
                'label' => [
                    'en-GB' => $setName,
                ],
            ],
            'relations' => $relations,
            'customFields' => $attributes,
        ];
        $this->attributeSets[$set['id']] = $set;
        $this->attributeSetRepository->upsert([$set], $context->getContext());
    }

    private function generateCustomFields($entityName, $count, array $attributes, DemodataContext $context): void
    {
        $repo = $this->definitionRegistry->getRepository($entityName);

        $ids = $this->connection->executeQuery(
            sprintf('SELECT LOWER(HEX(id)) FROM `%s` ORDER BY rand() LIMIT %s', $entityName, $count)
        )->fetchAll(FetchMode::COLUMN);

        $chunkSize = 50;
        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $updates = [];
            $attributeValues = [];
            foreach ($attributes as $attribute) {
                $attributeValues[$attribute['name']] = $this->randomCustomFieldValue($attribute['type'], $context->getFaker());
            }

            foreach ($chunk as $id) {
                $updates[] = ['id' => $id, 'attributes' => $attributeValues];
            }
            $repo->update($updates, $context->getContext());
        }
    }

    private function randomCustomFieldValue(string $type, Generator $faker)
    {
        switch ($type) {
            case CustomFieldTypes::BOOL:
                return (bool) random_int(0, 1);

            case CustomFieldTypes::FLOAT:
                return $faker->randomFloat();

            case CustomFieldTypes::INT:
                return random_int(-1000000, 1000000);

            case CustomFieldTypes::DATETIME:
                return $faker->dateTime;

            case CustomFieldTypes::TEXT:
            default:
                return $faker->text();
        }
    }
}
