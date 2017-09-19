<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\ShopTemplate\Repository\ShopTemplateRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.shop_template.api_controller", path="/api")
 */
class ShopTemplateController extends ApiController
{
    /**
     * @var ShopTemplateRepository
     */
    private $shopTemplateRepository;

    public function __construct(ShopTemplateRepository $shopTemplateRepository)
    {
        $this->shopTemplateRepository = $shopTemplateRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'shopTemplates';
    }

    public function getXmlChildKey(): string
    {
        return 'shopTemplate';
    }

    /**
     * @Route("/shopTemplate.{responseFormat}", name="api.shopTemplate.list", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
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
            $parser = new QueryStringParser();
            $criteria->addFilter(
                QueryStringParser::fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $searchResult = $this->shopTemplateRepository->searchUuids(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $shopTemplates = $this->shopTemplateRepository->read(
                    $searchResult->getUuids(),
                    $context->getShopContext()->getTranslationContext()
                );
                break;
            default:
                throw new \RuntimeException('Result format not supported.');
        }

        $response = [
            'data' => $shopTemplates,
            'total' => $searchResult->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shopTemplate/{shopTemplateUuid}.{responseFormat}", name="api.shopTemplate.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('shopTemplateUuid');
        $shopTemplates = $this->shopTemplateRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($shopTemplates->get($uuid), $context);
    }

    /**
     * @Route("/shopTemplate.{responseFormat}", name="api.shopTemplate.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->shopTemplateRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shopTemplates = $this->shopTemplateRepository->read(
            $createEvent->getShopTemplateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shopTemplates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shopTemplate.{responseFormat}", name="api.shopTemplate.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->shopTemplateRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shopTemplates = $this->shopTemplateRepository->read(
            $createEvent->getShopTemplateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shopTemplates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shopTemplate.{responseFormat}", name="api.shopTemplate.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->shopTemplateRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $shopTemplates = $this->shopTemplateRepository->read(
            $createEvent->getShopTemplateUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $shopTemplates,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/shopTemplate/{shopTemplateUuid}.{responseFormat}", name="api.shopTemplate.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('shopTemplateUuid');

        $updateEvent = $this->shopTemplateRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $shopTemplates = $this->shopTemplateRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $shopTemplates->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/shopTemplate.{responseFormat}", name="api.shopTemplate.delete", methods={"DELETE"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function deleteAction(ApiContext $context): Response
    {
        $result = [];

        return $this->createResponse($result, $context);
    }
}
