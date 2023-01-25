<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class LanguageSerializer extends EntitySerializer implements ResetInterface
{
    private array $cacheLanguages = [];

    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $languageRepository)
    {
    }

    /**
     * @param array|\Traversable $entity
     *
     * @return array|\Traversable
     */
    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        if (!isset($deserialized['id']) && isset($deserialized['locale']['code'])) {
            $language = $this->getLanguageSerialized($deserialized['locale']['code']);

            // if we dont find it by name, only set the id to the fallback if we dont have any other data
            if (!$language && \count($deserialized) === 1) {
                $deserialized['id'] = Defaults::LANGUAGE_SYSTEM;
                unset($deserialized['locale']);
            }

            if ($language) {
                $deserialized = array_merge_recursive($deserialized, $language);
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === LanguageDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheLanguages = [];
    }

    private function getLanguageSerialized(string $code): ?array
    {
        if (\array_key_exists($code, $this->cacheLanguages)) {
            return $this->cacheLanguages[$code];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('locale.code', $code));
        $criteria->addAssociation('locale');
        $language = $this->languageRepository->search($criteria, Context::createDefaultContext())->first();

        $this->cacheLanguages[$code] = null;
        if ($language instanceof LanguageEntity && $language->getLocale() !== null) {
            $this->cacheLanguages[$code] = [
                'id' => $language->getId(),
                'locale' => ['id' => $language->getLocale()->getId()],
            ];
        }

        return $this->cacheLanguages[$code];
    }
}
