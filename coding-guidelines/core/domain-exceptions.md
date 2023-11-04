# Domain exceptions

To ensure a consistent exception handling, we introduced domain exceptions. These domain exceptions are a separate exception class for each domain within shopware. These classes are used as a factory for all exceptions within the domain. The __construct of the DomainException is set to `private`, so that only the factory methods can create an instance.

Each domain exception class extends the `Shopware\Core\Framework\HttpException` class, which ensure a unique error code and http handling. Error codes of each domain exception class are unique within the domain. The error codes are defined within the corresponding domain exception.

Domain exception are always stored directly inside the top level domain in each area. Top level domains are:
- `Checkout\Cart`
- `Checkout\Customer`
- `Content\Category`
- `Content\Product`
- ...

This decision was made in this [ADR](https://github.com/shopware/platform/blob/71ef1dffc97a131069cd4649f71ba35d04771e24/adr/2022-02-24-domain-exceptions.md).

## Example
```php
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

#[Package('customer-order')]
class CustomerException extends HttpException
{
    public const CUSTOMER_GROUP_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_NOT_FOUND';

    public static function customerGroupNotFound(string $id): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_NOT_FOUND,
            'Customer group with id "{{ id }}" not found',
            ['id' => $id]
        );
    }
}
```

## Exceptions which should be catchable
However, the DomainExceptions are not (necessarily) made to be caught and handled in a try-catch. Therefore, we will continue to implement own exception classes, for exceptions that we want to catch ourselves in the system via a try-catch, which extends the DomainException. These exceptions are then stored in an exception sub folder:

```php
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

#[Package('customer-order')]
class CustomerException extends HttpException
{
    public const CUSTOMER_GROUP_NOT_FOUND = 'CHECKOUT__CUSTOMER_GROUP_NOT_FOUND';

    public static function notFound(string $id): self
    {
        return new CustomerNotFoundException(
            Response::HTTP_BAD_REQUEST,
            self::CUSTOMER_GROUP_NOT_FOUND,
            'Customer group with id "{{ id }}" not found',
            ['id' => $id]
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;

class CustomerNotFoundException extends CustomerException
{
}
```

## Http status code
Each specific type of domain exceptions should provide a specific http status code. Please use the following official http status defined by [https://developer.mozilla.org](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status) 
