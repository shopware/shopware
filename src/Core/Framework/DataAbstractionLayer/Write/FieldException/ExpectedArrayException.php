<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ExpectedArrayException extends DataAbstractionLayerException implements WriteFieldException
{
    public function __construct(string $path)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__WRITE_MALFORMED_INPUT',
            'Expected data at {{ path }} to be an array.',
            ['path' => $path]
        );
    }

    public function getPath(): string
    {
        return $this->getParameters()['path'];
    }
}
