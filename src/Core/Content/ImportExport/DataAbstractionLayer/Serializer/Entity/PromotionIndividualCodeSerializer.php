<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class PromotionIndividualCodeSerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @var array<string, string|null>
     */
    private array $cachePromoIds = [];

    /**
     * @var array<string, string|null>
     */
    private array $cachePromoCodeIds = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $promoCodeRepository,
        private readonly EntityRepository $promoRepository
    ) {
    }

    public function supports(string $entity): bool
    {
        return $entity === PromotionIndividualCodeDefinition::ENTITY_NAME;
    }

    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $entity = \is_array($entity) ? $entity : iterator_to_array($entity);

        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        // set promotion id from promotion name if possible
        if (empty($deserialized['promotion']['id']) && isset($entity['promotion']['translations']['DEFAULT']['name'])) {
            $promoId = $this->getPromoIdFromName($entity['promotion']['translations']['DEFAULT']['name']);

            if ($promoId) {
                $deserialized['promotion']['id'] = $promoId;
            }
        }

        // set promotion id to prevent failures
        if (empty($deserialized['promotion']['id']) && isset($deserialized['promotionId'])) {
            $deserialized['promotion']['id'] = $deserialized['promotionId'];
        }

        // set promotion useIndividualCodes to true if not specified otherwise
        // this ensures that the imported codes are needed for the promotion
        if (!isset($deserialized['promotion']['useIndividualCodes'])) {
            $deserialized['promotion']['useIndividualCodes'] = true;
        }

        // if useIndividualCodes is set -> also set useCodes because the admin needs it.
        if (
            !isset($deserialized['promotion']['useCodes'])
            && $deserialized['promotion']['useIndividualCodes'] === true
        ) {
            $deserialized['promotion']['useCodes'] = true;
        }

        // set promotionIndividualCode id from code if possible (for existing codes)
        if (empty($deserialized['id']) && isset($deserialized['promotion']['id']) && isset($entity['code'])) {
            $id = $this->getPromoCodeId($entity['code']);

            if ($id) {
                $deserialized['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function reset(): void
    {
        $this->cachePromoCodeIds = [];
        $this->cachePromoIds = [];
    }

    private function getPromoIdFromName(string $promotionName): ?string
    {
        if (\array_key_exists($promotionName, $this->cachePromoIds)) {
            return $this->cachePromoIds[$promotionName];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $promotionName));
        $promo = $this->promoRepository->search($criteria, Context::createDefaultContext())->first();

        $this->cachePromoIds[$promotionName] = null;
        if ($promo instanceof PromotionEntity) {
            $this->cachePromoIds[$promotionName] = $promo->getId();
        }

        return $this->cachePromoIds[$promotionName];
    }

    /**
     * Get the promotionIndividualCode id from the code (which is unique).
     */
    private function getPromoCodeId(string $code): ?string
    {
        if (\array_key_exists($code, $this->cachePromoCodeIds)) {
            return $this->cachePromoCodeIds[$code];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('code', $code));
        $promoCode = $this->promoCodeRepository->search($criteria, Context::createDefaultContext())->first();

        $this->cachePromoCodeIds[$code] = null;
        if ($promoCode instanceof PromotionIndividualCodeEntity) {
            $this->cachePromoCodeIds[$code] = $promoCode->getId();
        }

        return $this->cachePromoCodeIds[$code];
    }
}
