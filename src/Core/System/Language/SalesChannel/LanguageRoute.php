<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class LanguageRoute extends AbstractLanguageRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    /**
     * @var SalesChannelLanguageDefinition
     */
    private $languageDefinition;

    public function __construct(SalesChannelRepositoryInterface $languageRepository, RequestCriteriaBuilder $criteriaBuilder, SalesChannelLanguageDefinition $languageDefinition)
    {
        $this->languageRepository = $languageRepository;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->languageDefinition = $languageDefinition;
    }

    public function getDecorated(): AbstractLanguageRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @OA\Get(
     *      path="/language",
     *      description="Loads all available languages",
     *      operationId="readLanguages",
     *      tags={"Store API","Language"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="All available languages",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/language_flat"))
     *     )
     * )
     * @Route("/store-api/v{version}/language", name="store-api.language", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context): LanguageRouteResponse
    {
        $criteria = new Criteria();
        $criteria->addAssociation('translationCode');

        $criteria = $this->criteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->languageDefinition,
            $context->getContext()
        );

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository
            ->search($criteria, $context)
            ->getEntities();

        return new LanguageRouteResponse($languages);
    }
}
