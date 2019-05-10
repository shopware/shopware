<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Cache\CacheClearer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CacheController extends AbstractController
{
    /**
     * @var CacheClearer
     */
    private $cache;

    public function __construct(CacheClearer $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @Route("/api/v{version}/_action/cache", name="api.action.cache.delete", methods={"DELETE"})
     */
    public function clearCache(): JsonResponse
    {
        $this->cache->clear();

        return new JsonResponse();
    }
}
