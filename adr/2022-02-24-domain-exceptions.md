# 2022-02-24 - Domain exceptions

## Context

Until now, we have implemented many different exception classes in Shopware to map different exception cases. 
However, this pattern is very cumbersome for developers to maintain properly, which is why we often fall back on the old \RuntimeException. 
Another disadvantage of this pattern is that the system is overwhelmed with exception classes and therefore the overview of possible exceptions suffers.

## Solution
With the following pattern I would like to achieve the following goals:
- Developers can **no longer** just throw any **\RuntimeException** that can't be traced.
- Each exception has its **own error code**, which is passed to external APIs
- We **reduce the number of exception classes** we don't react to in the system (e.g. `\InvalidArgumentException`)

### Domain exceptions
We implement a separate exception class for each domain. This class is used as a factory for all exceptions within the domain.
The __construct of the DomainException is set to `private`, so that only the factory methods can create an instance.

```php
<?php

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class CmsException extends HttpException
{
    public const NOT_FOUND_CODE = 'CMS_NOT_FOUND';
    public const SOME_FOO_CODE = 'CMS_SOME_FOO';
    
    public static function notFound(?\Throwable $e = null): void
    {
        return new self(Response::HTTP_NOT_FOUND, self::NOT_FOUND_CODE, 'Cms page not found', [], $e);
    }

    public static function anExceptionIDontCatchAnywhere(?\Throwable $e = null) 
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::SOME_FOO_CODE, 'Some foo', [], $e);
    }
}
```

However, the DomainExceptions are not (necessarily) made to be caught and handled in a try-catch. Therefore, we will continue to implement own exception classes, for exceptions that we want to catch ourselves in the system via a `try-catch`, which extends the `DomainException`. These exceptions are then stored in an exception sub folder:

```php
<?php

use Shopware\Core\Framework\ShopwareHttpException;

// src/Core/Content/Cms/ProductException.php
namespace Shopware\Core\Content\Product {

    class ProductException extends ShopwareHttpException
    {
        public static function notFound(?\Throwable $e = null): void
        {
            return new ProductNotFoundException(Response::HTTP_NOT_FOUND, self::NOT_FOUND_CODE, 'Product page not found', [], $e);
        }
    }
}

// src/Core/Content/Product/Exception/NotFoundException.php
namespace Shopware\Core\Content\Product\Exception {
    class ProductNotFoundException extends ProductException { }
}

try {
    throw ProductException::notFound();
} catch (NotFoundException $e) {
    throw $e;
}
```
