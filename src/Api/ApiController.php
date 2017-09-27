<?php declare(strict_types=1);

namespace Shopware\Api;

use Shopware\Api\Exception\FormatNotSupportedException;
use Shopware\Product\Controller\XmlResponse;
use Shopware\Serializer\SerializerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    abstract public function getXmlRootKey(): string;

    abstract public function getXmlChildKey(): string;

    /**
     * @param mixed      $responseData
     * @param ApiContext $context
     * @param int        $statusCode
     *
     * @throws FormatNotSupportedException
     *
     * @return Response
     */
    protected function createResponse($responseData, ApiContext $context, int $statusCode = 200): Response
    {
        $responseEnvelope = $this->createEnvelope($responseData);
        $responseEnvelope->setParameters($context->getParameters());

        switch ($context->getOutputFormat()) {
            case 'json':
                $response = JsonResponse::create($responseEnvelope, $statusCode);
                break;
            case 'xml':
                $response = XmlResponse::createXmlResponse($this->getXmlRootKey(), $this->getXmlChildKey(), $responseEnvelope, $statusCode);
                break;
            case 'profile':
                if (true !== $this->container->getParameter('kernel.debug')) {
                    throw new \RuntimeException('Profiling is only allowed in debug mode.');
                }

                $response = $this->render('@Api/profile.html.twig', ['data' => $responseEnvelope]);
                break;
            default:
                throw new FormatNotSupportedException($context->getOutputFormat());
        }

        return $response;
    }

    private function createEnvelope(array $result): ResponseEnvelope
    {
        $response = new ResponseEnvelope();

        // todo: should be changed to something better than convetion
        if (array_key_exists('total', $result)) {
            $response->setTotal($result['total']);
        }

        if (array_key_exists('errors', $result)) {
            $response->setErrors($result['errors']);
        }

        $registry = $this->get('shopware.serializer.serializer_registry');

        $response->setData(
            $registry->serialize($result['data'], SerializerRegistry::FORMAT_API_JSON)
        );

        return $response;
    }
}
