<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
     * @Since("6.0.0.0")
     * Starts the dal indexing process in batch mode
     *
     * @Route("/api/_action/indexing", name="api.action.indexing", methods={"POST"})
     */
    public function indexing(Request $request): JsonResponse
    {
        $this->registry->sendIndexingMessage();

        return new JsonResponse();
    }

    /**
     * @Since("6.4.0.0")
     * Iterates the indexer
     *
     * @Route("/api/_action/indexing/{indexer}", name="api.action.indexing.iterate", methods={"POST"})
     */
    public function iterate(string $indexer, Request $request): JsonResponse
    {
        if (!$request->request->has('offset')) {
            throw new BadRequestHttpException('Parameter `offset` missing');
        }

        $indexer = $this->registry->getIndexer($indexer);

        $offset = ['offset' => $request->get('offset')];
        $message = $indexer ? $indexer->iterate($offset) : null;

        if ($message === null) {
            return new JsonResponse(['finish' => true]);
        }

        if ($indexer) {
            $indexer->handle($message);
        }

        return new JsonResponse(['finish' => false, 'offset' => $message->getOffset()]);
    }
}
