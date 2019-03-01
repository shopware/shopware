<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class XmlElementNotFoundException extends ShopwareHttpException
{
    protected $code = 'XML-ELEMENT-NOT-FOUND';

    public function __construct(string $element, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Unable to locate element with the name "%s".', $element);

        parent::__construct($message, $code, $previous);
    }
}
