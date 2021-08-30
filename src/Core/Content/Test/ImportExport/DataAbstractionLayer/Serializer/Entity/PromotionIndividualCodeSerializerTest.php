<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\PromotionDefinition;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\PromotionIndividualCodeSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class PromotionIndividualCodeSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $promoRepository;

    private EntityRepositoryInterface $promoCodeRepository;

    private PromotionIndividualCodeSerializer $serializer;

    private string $promoName = 'testPromo';

    private string $promoId = '';

    private string $promoCode = 'testCode';

    private string $promoCodeId = '';

    public function setUp(): void
    {
        $this->promoRepository = $this->getContainer()->get('promotion.repository');
        $this->promoCodeRepository = $this->getContainer()->get('promotion_individual_code.repository');
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);

        $this->serializer = new PromotionIndividualCodeSerializer(
            $this->promoCodeRepository,
            $this->promoRepository
        );
        $this->serializer->setRegistry($serializerRegistry);

        $this->promoId = $this->promoRepository->create([
            [
                'name' => $this->promoName,
            ],
        ], Context::createDefaultContext())
            ->getPrimaryKeys(PromotionDefinition::ENTITY_NAME)[0];

        $this->promoCodeId = $this->promoCodeRepository->create([
            [
                'code' => $this->promoCode,
                'promotionId' => $this->promoId,
            ],
        ], Context::createDefaultContext())
            ->getPrimaryKeys(PromotionIndividualCodeDefinition::ENTITY_NAME)[0];
    }

    public function testNonExistingPromo(): void
    {
        $config = new Config([], []);
        $promoCode = [
            'promotion' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => 'SomeOtherPromoName',
                    ],
                ],
                'id' => '',
                'useIndividualCodes' => 'false', // explicit override
            ],
            'code' => 'PrefixWXMPU',
            'id' => '',
        ];

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->promoCodeRepository->getDefinition(), $promoCode));

        static::assertSame([
            'promotion' => [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'SomeOtherPromoName',
                    ],
                ],
                'useIndividualCodes' => false,
            ],
            'code' => 'PrefixWXMPU',
        ], $deserialized);
    }

    public function testExistingPromoName(): void
    {
        $config = new Config([], []);
        $promoCode = [
            'promotion' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $this->promoName,
                    ],
                ],
                'id' => '',
            ],
            'code' => 'PrefixWXMPU',
            'id' => '',
        ];

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->promoCodeRepository->getDefinition(), $promoCode));

        static::assertSame([
            'promotion' => [
                'translations' => [
                    '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => [
                        'name' => $this->promoName,
                    ],
                ],
                'id' => $this->promoId,
                'useIndividualCodes' => true,
                'useCodes' => true,
            ],
            'code' => 'PrefixWXMPU',
        ], $deserialized);
    }

    public function testExistingPromoNameAndCode(): void
    {
        $config = new Config([], []);
        $promoCode = [
            'promotion' => [
                'translations' => [
                    'DEFAULT' => [
                        'name' => $this->promoName,
                    ],
                ],
                'id' => '',
            ],
            'code' => $this->promoCode,
            'id' => '',
        ];

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->promoCodeRepository->getDefinition(), $promoCode));

        static::assertSame([
            'promotion' => [
                'translations' => [
                    '2fbb5fe2e29a4d70aa5854ce7ce3e20b' => [
                        'name' => $this->promoName,
                    ],
                ],
                'id' => $this->promoId,
                'useIndividualCodes' => true,
                'useCodes' => true,
            ],
            'code' => 'testCode',
            'id' => $this->promoCodeId,
        ], $deserialized);
    }
}
