<?php declare(strict_types=1);

namespace Shopware\SeoUrl\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\SeoUrl\Repository\SeoUrlRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.seo_url.api_controller", path="/api")
 */
class SeoUrlController extends ApiController
{
    /**
     * @var SeoUrlRepository
     */
    private $seoUrlRepository;

    public function __construct(SeoUrlRepository $seoUrlRepository)
    {
        $this->seoUrlRepository = $seoUrlRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'seoUrls';
    }

    public function getXmlChildKey(): string
    {
        return 'seoUrl';
    }

    /**
     * @Route("/seoUrl.{responseFormat}", name="api.seoUrl.list", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function listAction(Request $request, ApiContext $context): Response
    {
        $criteria = new Criteria();

        if ($request->query->has('offset')) {
            $criteria->setOffset((int) $request->query->get('offset'));
        }

        if ($request->query->has('limit')) {
            $criteria->setLimit((int) $request->query->get('limit'));
        }

        if ($request->query->has('query')) {
            $criteria->addFilter(
                QueryStringParser::fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $searchResult = $this->seoUrlRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $seoUrls = $this->seoUrlRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $seoUrls,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/seoUrl/{seoUrlUuid}.{responseFormat}", name="api.seoUrl.detail", methods={"GET"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('seoUrlUuid');
        $seoUrls = $this->seoUrlRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($seoUrls->get($uuid), $context);
    }

    /**
     * @Route("/seoUrl.{responseFormat}", name="api.seoUrl.create", methods={"POST"})
     * @param ApiContext $context
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->seoUrlRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $seoUrls = $this->seoUrlRepository->read(
            $createEvent->getSeoUrlUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $seoUrls,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/seoUrl.{responseFormat}", name="api.seoUrl.upsert", methods={"PUT"})
     * @param ApiContext $context
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->seoUrlRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $seoUrls = $this->seoUrlRepository->read(
            $createEvent->getSeoUrlUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $seoUrls,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/seoUrl.{responseFormat}", name="api.seoUrl.update", methods={"PATCH"})
     * @param ApiContext $context
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->seoUrlRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $seoUrls = $this->seoUrlRepository->read(
            $createEvent->getSeoUrlUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $seoUrls,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/seoUrl/{seoUrlUuid}.{responseFormat}", name="api.seoUrl.single_update", methods={"PATCH"})
     * @param Request $request
     * @param ApiContext $context
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('seoUrlUuid');

        $updateEvent = $this->seoUrlRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $seoUrls = $this->seoUrlRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $seoUrls->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/seoUrl.{responseFormat}", name="api.seoUrl.delete", methods={"DELETE"})
     * @param ApiContext $context
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
