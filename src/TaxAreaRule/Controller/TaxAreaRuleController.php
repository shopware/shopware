<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Search\Criteria;
use Shopware\Search\Parser\QueryStringParser;
use Shopware\TaxAreaRule\Repository\TaxAreaRuleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.tax_area_rule.api_controller", path="/api")
 */
class TaxAreaRuleController extends ApiController
{
    /**
     * @var TaxAreaRuleRepository
     */
    private $taxAreaRuleRepository;

    public function __construct(TaxAreaRuleRepository $taxAreaRuleRepository)
    {
        $this->taxAreaRuleRepository = $taxAreaRuleRepository;
    }

    public function getXmlRootKey(): string
    {
        return 'taxAreaRules';
    }

    public function getXmlChildKey(): string
    {
        return 'taxAreaRule';
    }

    /**
     * @Route("/taxAreaRule.{responseFormat}", name="api.taxAreaRule.list", methods={"GET"})
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
            $criteria->addFilter(
                QueryStringParser::fromUrl($request->query->get('query'))
            );
        }

        $criteria->setFetchCount(true);

        $taxAreaRules = $this->taxAreaRuleRepository->search(
            $criteria,
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxAreaRules->getElements(),
            'total' => $taxAreaRules->getTotal(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/taxAreaRule/{taxAreaRuleUuid}.{responseFormat}", name="api.taxAreaRule.detail", methods={"GET"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function detailAction(Request $request, ApiContext $context): Response
    {
        $uuid = $request->get('taxAreaRuleUuid');
        $taxAreaRules = $this->taxAreaRuleRepository->read(
            [$uuid],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse($taxAreaRules->get($uuid), $context);
    }

    /**
     * @Route("/taxAreaRule.{responseFormat}", name="api.taxAreaRule.create", methods={"POST"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function createAction(ApiContext $context): Response
    {
        $createEvent = $this->taxAreaRuleRepository->create(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $taxAreaRules = $this->taxAreaRuleRepository->read(
            $createEvent->getTaxAreaRuleUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxAreaRules,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/taxAreaRule.{responseFormat}", name="api.taxAreaRule.upsert", methods={"PUT"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function upsertAction(ApiContext $context): Response
    {
        $createEvent = $this->taxAreaRuleRepository->upsert(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $taxAreaRules = $this->taxAreaRuleRepository->read(
            $createEvent->getTaxAreaRuleUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxAreaRules,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/taxAreaRule.{responseFormat}", name="api.taxAreaRule.update", methods={"PATCH"})
     *
     * @param ApiContext $context
     *
     * @return Response
     */
    public function updateAction(ApiContext $context): Response
    {
        $createEvent = $this->taxAreaRuleRepository->update(
            $context->getPayload(),
            $context->getShopContext()->getTranslationContext()
        );

        $taxAreaRules = $this->taxAreaRuleRepository->read(
            $createEvent->getTaxAreaRuleUuids(),
            $context->getShopContext()->getTranslationContext()
        );

        $response = [
            'data' => $taxAreaRules,
            'errors' => $createEvent->getErrors(),
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/taxAreaRule/{taxAreaRuleUuid}.{responseFormat}", name="api.taxAreaRule.single_update", methods={"PATCH"})
     *
     * @param Request    $request
     * @param ApiContext $context
     *
     * @return Response
     */
    public function singleUpdateAction(Request $request, ApiContext $context): Response
    {
        $payload = $context->getPayload();
        $payload['uuid'] = $request->get('taxAreaRuleUuid');

        $updateEvent = $this->taxAreaRuleRepository->update(
            [$payload],
            $context->getShopContext()->getTranslationContext()
        );

        if ($updateEvent->hasErrors()) {
            $errors = $updateEvent->getErrors();
            $error = array_shift($errors);

            return $this->createResponse(['errors' => $error], $context, 400);
        }

        $taxAreaRules = $this->taxAreaRuleRepository->read(
            [$payload['uuid']],
            $context->getShopContext()->getTranslationContext()
        );

        return $this->createResponse(
            ['data' => $taxAreaRules->get($payload['uuid'])],
            $context
        );
    }

    /**
     * @Route("/taxAreaRule.{responseFormat}", name="api.taxAreaRule.delete", methods={"DELETE"})
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
