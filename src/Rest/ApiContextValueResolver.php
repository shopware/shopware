<?php declare(strict_types=1);

namespace Shopware\Rest;

use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\EntitySearcherInterface;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Storefront\Session\ShopSubscriber;
use Shopware\Api\User\Definition\UserDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiContextValueResolver implements ArgumentValueResolverInterface
{
    public const OUTPUT_FORMAT_PARAMETER_NAME = 'responseFormat';
    public const RESULT_FORMAT_PARAMETER_NAME = '_resultFormat';
    public const SUPPORTED_FORMATS = ['json', 'xml'];

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EntitySearcherInterface
     */
    private $searcher;

    /**
     * @var array
     */
    private $mapping = [];

    public function __construct(TokenStorageInterface $tokenStorage, EntitySearcherInterface $searcher)
    {
        $this->tokenStorage = $tokenStorage;
        $this->searcher = $searcher;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ApiContext::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $outputFormat = $request->get(self::OUTPUT_FORMAT_PARAMETER_NAME, 'json');
        $resultFormat = $request->get(self::RESULT_FORMAT_PARAMETER_NAME, ResultFormat::BASIC);

        $payload = $this->getPayload($request, $outputFormat);
        $parameters = $request->query;

        /** @var ShopContext $shopContext */
        $shopContext = $request->attributes->get(ShopSubscriber::SHOP_CONTEXT_PROPERTY);

        yield new ApiContext(
            $payload,
            $shopContext,
            $this->getUserUuid($shopContext->getTranslationContext()),
            $parameters->all(),
            $outputFormat,
            $resultFormat
        );
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

    private function getUserUuid(TranslationContext $context): string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return ApiContext::KERNEL_USER;
        }

        /** @var UserInterface $user */
        $user = $token->getUser();

        $name = $user->getUsername();
        if (array_key_exists($name, $this->mapping)) {
            return $this->mapping[$name];
        }

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new TermQuery(UserDefinition::getEntityName() . '.username', $name));

        $users = $this->searcher->search(UserDefinition::class, $criteria, $context);
        $uuids = $users->getUuids();

        $uuid = array_shift($uuids);

        if (!$uuid) {
            return $this->mapping[$name] = ApiContext::KERNEL_USER;
        }

        return $this->mapping[$name] = $uuid;
    }
}
