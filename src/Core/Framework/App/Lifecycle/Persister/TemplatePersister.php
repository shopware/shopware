<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Template\AbstractTemplateLoader;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class TemplatePersister
{
    /**
     * @var AbstractTemplateLoader
     */
    private $templateLoader;

    /**
     * @var EntityRepositoryInterface
     */
    private $templateRepository;

    public function __construct(AbstractTemplateLoader $templateLoader, EntityRepositoryInterface $templateRepository)
    {
        $this->templateLoader = $templateLoader;
        $this->templateRepository = $templateRepository;
    }

    public function updateTemplates(Manifest $manifest, string $appId, Context $context): void
    {
        $existingTemplates = $this->getExistingTemplates($appId, $context);
        $templatePaths = $this->templateLoader->getTemplatePathsForApp($manifest);

        $upserts = [];
        foreach ($templatePaths as $templatePath) {
            $payload = [
                'template' => $this->templateLoader->getTemplateContent($templatePath, $manifest),
            ];

            /** @var TemplateEntity|null $existing */
            $existing = $existingTemplates->filterByProperty('path', $templatePath)->first();
            if ($existing) {
                $payload['id'] = $existing->getId();
                $existingTemplates->remove($existing->getId());
            } else {
                $payload['appId'] = $appId;
                $payload['active'] = false;
                $payload['path'] = $templatePath;
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $this->templateRepository->upsert($upserts, $context);
        }

        $this->deleteOldTemplates($existingTemplates, $context);
    }

    private function deleteOldTemplates(TemplateCollection $toBeRemoved, Context $context): void
    {
        /** @var string[] $ids */
        $ids = $toBeRemoved->getIds();

        if (!empty($ids)) {
            $ids = array_map(static function (string $id): array {
                return ['id' => $id];
            }, array_values($ids));

            $this->templateRepository->delete($ids, $context);
        }
    }

    private function getExistingTemplates(string $appId, Context $context): TemplateCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));

        /** @var TemplateCollection $templates */
        $templates = $this->templateRepository->search($criteria, $context)->getEntities();

        return $templates;
    }
}
