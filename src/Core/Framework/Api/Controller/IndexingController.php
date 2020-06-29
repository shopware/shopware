<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 */
class IndexingController extends AbstractController
{
    /**
     * @var EntityIndexerRegistry
     */
    private $registry;

    public function __construct(EntityIndexerRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Starts the dal indexing process in batch mode
     *
     * @Route("/api/v{version}/_action/indexing", name="api.action.indexing", methods={"POST"})
     */
    public function indexing(Request $request): JsonResponse
    {
        $this->registry->sendIndexingMessage();

        return new JsonResponse();
    }
}
