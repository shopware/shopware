<?php declare(strict_types=1);

namespace Shopware\Rest;

use Shopware\Storefront\Session\ShopSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApiContextValueResolver implements ArgumentValueResolverInterface
{
    const OUTPUT_FORMAT_PARAMETER_NAME = 'responseFormat';
    const RESULT_FORMAT_PARAMETER_NAME = '_resultFormat';
    const SUPPORTED_FORMATS = ['json', 'xml'];

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ApiContext::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $outputFormat = $request->get(self::OUTPUT_FORMAT_PARAMETER_NAME);
        $resultFormat = $request->get(self::RESULT_FORMAT_PARAMETER_NAME, ResultFormat::BASIC);

        $outputFormat = 'json';
        $payload = $this->getPayload($request, $outputFormat);
        $parameters = $request->query;
        $shopContext = $request->attributes->get(ShopSubscriber::SHOP_CONTEXT_PROPERTY);

        yield new ApiContext($payload, $shopContext, $parameters->all(), $outputFormat, $resultFormat);
    }

    private function getPayload(Request $request, string $format)
    {
        if ($request->request->count()) {
            return $request->request->all();
        }

        $payload = null;
        $error = null;

        switch ($format) {
            case 'json':
                $payload = json_decode($request->getContent(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $error = json_last_error_msg();
                }
                break;
            case 'xml':
                $xml = simplexml_load_string($request->getContent());
                $rawArray = json_decode(json_encode($xml), true);
                $error = 'XML syntax error';

                $payload = $rawArray['product'];
                break;
        }

        if (!empty($request->getContent()) && $error) {
            throw new BadRequestHttpException(sprintf('Request content is malformed. (Error: %s)', $error));
        }

        return $payload ?? [];
    }
}
