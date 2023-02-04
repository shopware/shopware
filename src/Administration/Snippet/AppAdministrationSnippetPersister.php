<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleException;

/**
 * @internal
 */
#[Package('administration')]
class AppAdministrationSnippetPersister
{
    public function __construct(
        private readonly EntityRepository $appAdministrationSnippetRepository,
        private readonly EntityRepository $localeRepository
    ) {
    }

    /**
     * @param array<string, string> $snippets
     */
    public function updateSnippets(AppEntity $app, array $snippets, Context $context): void
    {
        $newOrUpdatedSnippets = [];
        $existingSnippets = $this->getExistingSnippets($app->getId(), $context);
        $coreSnippets = $this->getCoreAdministrationSnippets();

        $firstLevelSnippetKeys = [];
        foreach ($snippets as $snippetString) {
            $decodedSnippets = json_decode($snippetString, true, 512, \JSON_THROW_ON_ERROR);
            $firstLevelSnippetKeys = array_keys($decodedSnippets);
        }

        if ($duplicatedKeys = array_intersect(array_keys($coreSnippets), $firstLevelSnippetKeys)) {
            throw SnippetException::extendOrOverwriteCore($duplicatedKeys);
        }

        // only throw exception if snippets are given but not en-GB
        if (!\array_key_exists('en-GB', $snippets) && !empty($snippets)) {
            throw SnippetException::defaultLanguageNotGiven('en-GB');
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('code', array_keys($snippets)));
        $localeIds = $this->localeRepository->search($criteria, $context)->getEntities()->getElements();
        $localeIds = array_column($localeIds, 'id', 'code');

        $existingLocales = [];
        foreach ($existingSnippets as $snippetEntity) {
            $existingLocales[$snippetEntity->getLocaleId()] = $snippetEntity->getId();
        }

        foreach ($snippets as $filename => $value) {
            if (!\array_key_exists($filename, $localeIds)) {
                throw LocaleException::localeDoesNotExists($filename);
            }

            $localeId = $localeIds[$filename];
            $id = Uuid::randomHex();

            if (\array_key_exists($localeId, $existingLocales)) {
                $id = $existingLocales[$localeId];
                unset($existingLocales[$localeId]);
            }

            $newOrUpdatedSnippets[] = [
                'id' => $id,
                'value' => $value,
                'appId' => $app->getId(),
                'localeId' => $localeIds[$filename],
            ];
        }

        $this->appAdministrationSnippetRepository->upsert($newOrUpdatedSnippets, $context);

        // if locale is given --> upsert, if not given --> delete
        $deletedIds = array_values($existingLocales);
        $this->deleteSnippets($deletedIds, $context);
    }

    private function getExistingSnippets(string $appId, Context $context): AppAdministrationSnippetCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        /** @var AppAdministrationSnippetCollection $collection */
        $collection = $this->appAdministrationSnippetRepository->search($criteria, $context)->getEntities();

        return $collection;
    }

    /**
     * @return array<string, mixed>
     */
    private function getCoreAdministrationSnippets(): array
    {
        $path = __DIR__ . '/../Resources/app/administration/src/app/snippet/en-GB.json';
        $snippets = file_get_contents($path);

        if (!$snippets) {
            return [];
        }

        return json_decode($snippets, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $ids
     */
    private function deleteSnippets(array $ids, Context $context): void
    {
        $data = [];
        foreach ($ids as $id) {
            $data[] = ['id' => $id];
        }

        $this->appAdministrationSnippetRepository->delete($data, $context);
    }
}
