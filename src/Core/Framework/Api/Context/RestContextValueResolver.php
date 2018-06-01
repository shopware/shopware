<?php declare(strict_types=1);

namespace Shopware\Framework\Api\Context;

use Shopware\Framework\Context;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Framework\ORM\Search\Query\TermQuery;
use Shopware\PlatformRequest;
use Shopware\System\User\UserDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class RestContextValueResolver implements ArgumentValueResolverInterface
{
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
        return $argument->getType() === RestContext::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        yield new RestContext(
            $request,
            $context,
            $this->getUserId($context)
        );
    }

    private function getUserId(Context $context): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return null;
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
        $ids = $users->getIds();

        $id = array_shift($ids);

        if (!$id) {
            return $this->mapping[$name] = null;
        }

        return $this->mapping[$name] = $id;
    }
}
