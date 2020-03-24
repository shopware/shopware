<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class PriceSerializer extends FieldSerializer
{
    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    public function __construct(EntityRepositoryInterface $currencyRepository)
    {
        $this->currencyRepository = $currencyRepository;
    }

    public function serialize(Field $entity, $prices): iterable
    {
        if (!$prices) {
            return;
        }

        $isoPrices = [];
        foreach ($prices as $currencyId => $price) {
            $currency = $this->mapToCurrencyIso($currencyId);
            $isoPrices[$currency] = $price instanceof Struct ? $price->jsonSerialize() : $price;
            if ($currencyId === Defaults::CURRENCY) {
                $isoPrices['DEFAULT'] = $isoPrices[$currency];
            }
        }

        yield $entity->getPropertyName() => $isoPrices;
    }

    public function deserialize(Field $field, $record): ?array
    {
        $prices = [];

        foreach ($record as $curIso => $price) {
            $cur = $this->getCurrencyIdFromIso($curIso);

            if ($cur === null) {
                continue;
            }

            $p = new Price($cur, (float) $price['net'], (float) $price['gross'], (bool) ($price['linked'] ?? false));
            $prices[$cur] = $p->jsonSerialize();
        }

        return $prices;
    }

    public function supports(Field $field): bool
    {
        return $field instanceof PriceField;
    }

    public function setRegistry(SerializerRegistry $serializerRegistry): void
    {
    }

    private function mapToCurrencyIso(string $currencyId): string
    {
        $currency = $this->currencyRepository
            ->search(new Criteria([$currencyId]), Context::createDefaultContext())
            ->first();

        return $currency ? $currency->getIsoCode() : $currencyId;
    }

    private function getCurrencyIdFromIso(string $iso): ?string
    {
        if ($iso === 'DEFAULT') {
            return Defaults::CURRENCY;
        }

        if (Uuid::isValid($iso)) {
            $criteria = new Criteria([$iso]);
        } else {
            $criteria = (new Criteria())->addFilter(new EqualsFilter('isoCode', $iso));
        }

        /** @var CurrencyEntity|null $currency */
        $currency = $this->currencyRepository->search($criteria, Context::createDefaultContext())->first();

        return $currency === null ? null : $currency->getId();
    }
}
