<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Api;

use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockCollection;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 *
 * @RouteScope(scopes={"api"})
 */
class AppCmsController extends AbstractController
{
    /**
     * @internal (flag:FEATURE_NEXT_14408) - make not nullable after feature roll-out
     */
    private ?EntityRepositoryInterface $cmsBlockRepository;

    public function __construct(
        ?EntityRepositoryInterface $cmsBlockRepository
    ) {
        $this->cmsBlockRepository = $cmsBlockRepository;
    }

    /**
     * @Since("6.4.2.0")
     * @Route("api/app-system/cms/blocks", name="api.app_system.cms.blocks", methods={"GET"})
     */
    public function getBlocks(Context $context): Response
    {
        if (!Feature::isActive('FEATURE_NEXT_14408') || $this->cmsBlockRepository === null) {
            throw new NotFoundHttpException();
        }

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('app.active', true))
            ->addSorting(new FieldSorting('name'));
        /** @var AppCmsBlockCollection $blocks */
        $blocks = $this->cmsBlockRepository->search($criteria, $context)->getEntities();

        return new JsonResponse(['blocks' => $this->formatBlocks($blocks)]);
    }

    private function formatBlocks(AppCmsBlockCollection $blocks): array
    {
        $formattedBlocks = [];

        /** @var AppCmsBlockEntity $block */
        foreach ($blocks as $block) {
            $formattedBlock = $block->getBlock();
            $formattedBlock['template'] = $block->getTemplate();
            $formattedBlock['styles'] = $block->getStyles();

            $formattedBlocks[] = $formattedBlock;
        }

        return $formattedBlocks;
    }
}
