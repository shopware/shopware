<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('core')]
class AppCmsController extends AbstractController
{
    /**
     * @param EntityRepository<AppCmsBlockCollection> $cmsBlockRepository
     */
    public function __construct(private readonly EntityRepository $cmsBlockRepository)
    {
    }

    #[Route(path: 'api/app-system/cms/blocks', name: 'api.app_system.cms.blocks', methods: ['GET'])]
    public function getBlocks(Context $context): Response
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('app.active', true))
            ->addSorting(new FieldSorting('name'));
        $blocks = $this->cmsBlockRepository->search($criteria, $context)->getEntities();

        return new JsonResponse(['blocks' => $this->formatBlocks($blocks)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatBlocks(AppCmsBlockCollection $blocks): array
    {
        $formattedBlocks = [];

        foreach ($blocks as $block) {
            $formattedBlock = $block->getBlock();
            $formattedBlock['template'] = $block->getTemplate();
            $formattedBlock['styles'] = $block->getStyles();

            $formattedBlocks[] = $formattedBlock;
        }

        return $formattedBlocks;
    }
}
