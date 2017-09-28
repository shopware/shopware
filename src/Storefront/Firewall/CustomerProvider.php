<?php declare(strict_types=1);

namespace Shopware\Storefront\Firewall;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Repository\CustomerRepository;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermQuery;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class CustomerProvider implements UserProviderInterface
{
    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * CustomerProvider constructor.
     *
     * @param CustomerRepository $customerRepository
     */
    public function __construct(CustomerRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function loadUserByUsername($email)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('email', $email));
        $criteria->setLimit(1);

        $context = new TranslationContext('SWAG-SHOP-UUID-1', true, null);
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
        $customerUser->setUuid($customer->getUuid());

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
