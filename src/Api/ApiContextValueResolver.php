<?php declare(strict_types=1);


namespace Shopware\Api;

use Shopware\Storefront\Session\ShopSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ApiContextValueResolver implements ArgumentValueResolverInterface
{
    const OUTPUT_FORMAT_PARAMETER_NAME = 'responseFormat';
    const RESULT_FORMAT_PARAMETER_NAME = '_resultFormat';
    const SUPPORTED_FORMATS = ['json', 'xml'];

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return ApiContext::class === $argument->getType();
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $outputFormat = $request->get(self::OUTPUT_FORMAT_PARAMETER_NAME);
        $resultFormat = $request->get(self::RESULT_FORMAT_PARAMETER_NAME, ResultFormat::BASIC);

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

        switch ($format) {
            case 'json':
                return json_decode($request->getContent(), true);
            case 'xml':
                $xml = simplexml_load_string($request->getContent());
                $rawArray = json_decode(json_encode($xml), true);

                return $rawArray['product'];
        }

        return [];
    }
}
