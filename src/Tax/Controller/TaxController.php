<?php declare(strict_types=1);

namespace Shopware\Tax\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\ApiContext;
use Shopware\Api\ApiController;
use Shopware\Api\ResultFormat;
use Shopware\Tax\TaxRepository;
use Shopware\Search\Criteria;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="shopware.tax.controller.tax_controller", path="/api")
 */
class TaxController extends ApiController
{
    public function getXmlRootKey(): string
    {
        return 'taxes';
    }

    public function getXmlChildKey(): string
    {
        return 'tax';
    }

    /**
     * @var TaxRepository
     */
    private $taxRepository;

    public function __construct(TaxRepository $taxRepository)
    {
        $this->taxRepository = $taxRepository;
    }

    /**
     * @Route("/tax.{responseFormat}", name="api.tax.list", methods={"GET"})
     */
    public function listAction(Request $request, ApiContext $context): Response
    {
        $criteria = new Criteria();

        if ($request->query->has('offset')) {
            $criteria->offset($request->query->get('offset'));
        }

        if ($request->query->has('limit')) {
            $criteria->limit($request->query->get('limit'));
        }

        $criteria->setFetchCount(true);

        $searchResult = $this->taxRepository->search($criteria, $context->getShopContext()->getTranslationContext());

        switch ($context->getResultFormat()) {
            case ResultFormat::BASIC:
                $taxes = $this->taxRepository->read($searchResult->getUuids(), $context->getShopContext()->getTranslationContext());
                break;
            case ResultFormat::BASIC_NEXUS:
                $taxes = $this->taxBackendRepository->readBasic($searchResult->getUuids(), $context->getShopContext());
                break;
            default:
                throw new \Exception("Result format not supported.");
        }

        $response = [
            'data' => $taxes,
            'total' => $searchResult->getTotal()
        ];

        return $this->createResponse($response, $context);
    }

    /**
     * @Route("/tax/{taxUuid}.{responseFormat}", name="api.tax.detail", methods={"GET"})
     */
    public function detailAction(Request $request, ApiContext $context)
    {
        $uuid = $request->get('taxUuid');

        $taxes = $this->taxRepository->read([$uuid], $context->getShopContext()->getTranslationContext());
        $tax = $taxes->get($uuid);

        return $this->createResponse($tax, $context);
    }
}
