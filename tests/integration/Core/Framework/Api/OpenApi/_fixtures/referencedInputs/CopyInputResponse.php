<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\CustomOperation;

use Shopware\Core\Example\Shared\InputSettings;
use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Copied input
 */
#[Package('framework')]
final class CopyInputResponse extends AbstractResponse
{
    #[Assert\Valid]
    public InputSettings $settings;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public bool $copied,
    ) {
        parent::__construct(statusCode: Response::HTTP_CREATED);
    }
}
