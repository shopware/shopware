<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Firewall;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerBasicStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CustomerProvider implements UserProviderInterface
{
    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * CustomerProvider constructor.
     *
     * @param RepositoryInterface $customerRepository
     */
    public function __construct(RepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function loadUserByUsername($email)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('customer.email', $email));
        $criteria->setLimit(1);

        //todo@jb use tenant id of current request or console command
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $customerResult = $this->customerRepository->search($criteria, $context);

        // pretend it returns an array on success, false if there is no user
        if ($customerResult->getTotal() === 0) {
            throw new UsernameNotFoundException(
                sprintf('Customer with email address "%s" does not exist.', $email)
            );
        }

        /** @var CustomerBasicStruct $customer */
        $customer = $customerResult->first();

        $customerUser = new CustomerUser($customer->getEmail(), $customer->getPassword(), null, ['IS_AUTHENTICATED_FULLY', 'ROLE_CUSTOMER']);
        $customerUser->setId($customer->getId());

        return $customerUser;
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof CustomerUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === CustomerUser::class;
    }
}
