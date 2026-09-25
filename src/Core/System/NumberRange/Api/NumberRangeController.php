<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\Api;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\NumberRange\NumberRangeException;
use Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class NumberRangeController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<NumberRangeCollection> $numberRangeRepository
     */
    public function __construct(
        private readonly AbstractNumberRangeValueGenerator $valueGenerator,
        private readonly EntityRepository $numberRangeRepository,
    ) {
    }

    #[Cache(mustRevalidate: true)]
    #[Route(
        path: '/api/_action/number-range/reserve/{type}/{saleschannel?}',
        name: 'api.action.number-range.reserve',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['number_range:read']],
        methods: ['GET']
    )]
    public function reserve(string $type, ?string $saleschannel, Context $context, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->getValue($type, $context, $saleschannel, $request->query->getBoolean('preview'));

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }

    /**
     * @deprecated tag:v6.8.0 - use previewPatternByNumberRange() with a concrete number range id instead
     */
    #[Cache(mustRevalidate: true)]
    #[Route(
        path: '/api/_action/number-range/preview-pattern/{type}',
        name: 'api.action.number-range.preview-pattern',
        defaults: ['type' => 'default', PlatformRequest::ATTRIBUTE_ACL => ['number_range:read']],
        methods: ['GET']
    )]
    public function previewPattern(string $type, Request $request): JsonResponse
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '/api/_action/number-range/{numberRangeId}/preview-pattern')
        );

        $generatedNumber = Feature::silent(
            'v6.8.0.0',
            fn (): string => $this->valueGenerator->previewPattern(
                $type,
                $request->query->has('pattern') ? (string) $request->query->get('pattern') : null,
                (int) $request->query->get('start')
            )
        );

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }

    #[Cache(mustRevalidate: true)]
    #[Route(
        path: '/api/_action/number-range/{numberRangeId}/preview-pattern',
        name: 'api.action.number-range.preview-pattern-by-id',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['number_range:read']],
        requirements: ['numberRangeId' => Uuid::VALID_PATTERN],
        methods: ['GET']
    )]
    public function previewPatternByNumberRange(string $numberRangeId, Request $request): JsonResponse
    {
        $generatedNumber = $this->valueGenerator->previewPatternByNumberRangeId(
            $numberRangeId,
            $request->query->has('pattern') ? (string) $request->query->get('pattern') : null,
            $request->query->has('start') ? (int) $request->query->get('start') : null
        );

        return new JsonResponse([
            'number' => $generatedNumber,
        ]);
    }

    #[Cache(mustRevalidate: true)]
    #[Route(
        path: '/api/_action/number-range/pattern-collisions',
        name: 'api.action.number-range.pattern-collisions',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['number_range:read']],
        methods: ['GET']
    )]
    public function patternCollisions(Request $request, Context $context): JsonResponse
    {
        $typeId = (string) $request->query->get('typeId');
        $pattern = (string) $request->query->get('pattern');
        $numberRangeId = $request->query->has('numberRangeId') ? (string) $request->query->get('numberRangeId') : null;

        if ($typeId === '') {
            throw NumberRangeException::missingRequestParameter('typeId');
        }

        if (!Uuid::isValid($typeId)) {
            throw NumberRangeException::invalidRequestParameter('typeId');
        }

        if ($pattern === '') {
            throw NumberRangeException::missingRequestParameter('pattern');
        }

        if ($numberRangeId !== null && !Uuid::isValid($numberRangeId)) {
            throw NumberRangeException::invalidRequestParameter('numberRangeId');
        }

        // Document numbers are unique per document type, so only document number ranges can collide.
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('typeId', $typeId),
            new EqualsFilter('pattern', $pattern),
            new PrefixFilter('type.technicalName', DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX),
        );

        if ($numberRangeId !== null) {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id', $numberRangeId)]));
        }

        $numberRanges = $this->numberRangeRepository->search($criteria, $context)->getEntities();

        $collisions = [];
        foreach ($numberRanges as $numberRange) {
            $collisions[] = [
                'id' => $numberRange->getId(),
                'name' => $numberRange->getTranslation('name') ?? $numberRange->getName(),
            ];
        }

        return new JsonResponse(['collisions' => $collisions]);
    }
}
