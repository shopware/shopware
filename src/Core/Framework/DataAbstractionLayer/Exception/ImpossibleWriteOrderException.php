<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ImpossibleWriteOrderException extends DataAbstractionLayerException
{
    public const IMPOSSIBLE_WRITE_ORDER = 'FRAMEWORK__IMPOSSIBLE_WRITE_ORDER';

    /**
     * @param list<string> $remaining
     */
    public function __construct(array $remaining)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::IMPOSSIBLE_WRITE_ORDER,
            'Can not resolve write order for provided data. Remaining write order classes: {{ classesString }}',
            ['classes' => $remaining, 'classesString' => implode(', ', $remaining)]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__IMPOSSIBLE_WRITE_ORDER';
    }
}
