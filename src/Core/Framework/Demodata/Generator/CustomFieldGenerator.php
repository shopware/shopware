<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Demodata\DemodataContext;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
#[Package('core')]
class CustomFieldGenerator implements DemodataGeneratorInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $attributeSets = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $attributeSetRepository,
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $definitionRegistry
    ) {
    }

    public function getDefinition(): string
    {
        return CustomFieldSetDefinition::class;
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
        $sum = (int) array_sum($relations);
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

    /**
     * @return array<string, mixed>
     */
    private function getRandomSet(): array
    {
        return $this->attributeSets[array_rand($this->attributeSets)];
    }

    /**
     * @return array<string, mixed>
     */
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

        $config = match ($type) {
            CustomFieldTypes::INT => [
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
            ],
            CustomFieldTypes::FLOAT => [
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
            ],
            CustomFieldTypes::DATETIME => [
                'componentName' => 'sw-field',
                'type' => 'date',
                'dateType' => 'datetime',
                'customFieldType' => 'date',
                'label' => [
                    'en-GB' => $name,
                ],
                'customFieldPosition' => 1,
            ],
            CustomFieldTypes::BOOL => [
                'componentName' => 'sw-field',
                'type' => 'checkbox',
                'customFieldType' => 'checkbox',
                'label' => [
                    'en-GB' => $name,
                ],
                'customFieldPosition' => 1,
            ],
            default => [
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
            ],
        };

        \assert(\is_string($name));

        return [
            'id' => Uuid::randomHex(),
            'name' => mb_strtolower($prefix) . '_' . str_replace(' ', '_', $name),
            'type' => $type,
            'config' => $config,
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function generateCustomFieldSet(array $options, DemodataContext $context): void
    {
        $relationNames = array_keys($options['relations']);
        $relations = array_map(static fn ($rel) => ['id' => Uuid::randomHex(), 'entityName' => $rel], $relationNames);

        $attributeCount = random_int(1, 5);
        $attributes = [];

        $setName = $context->getFaker()->unique()->format('customFieldSet');
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

    /**
     * @param list<array<string, mixed>> $attributes
     */
    private function generateCustomFields(string $entityName, int $count, array $attributes, DemodataContext $context): void
    {
        $repo = $this->definitionRegistry->getRepository($entityName);

        $ids = $this->connection->fetchFirstColumn(
            sprintf('SELECT LOWER(HEX(id)) FROM `%s` ORDER BY rand() LIMIT %d', $entityName, $count)
        );

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

    /**
     * @return mixed
     */
    private function randomCustomFieldValue(string $type, Generator $faker)
    {
        return match ($type) {
            CustomFieldTypes::BOOL => (bool) random_int(0, 1),
            CustomFieldTypes::FLOAT => $faker->randomFloat(),
            CustomFieldTypes::INT => random_int(-1000000, 1000000),
            CustomFieldTypes::DATETIME => $faker->dateTime(),
            default => $faker->text(),
        };
    }
}
