<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Created inline payload
 */
#[Package('framework')]
final class CreateInlinePayloadResponse extends AbstractResponse
{
    #[Assert\Valid]
    public Settings $settings;

    #[Assert\Valid]
    public CreateInlinePayloadResponseDetails $details;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public string $id,
    ) {
        parent::__construct(statusCode: Response::HTTP_CREATED);
    }
}
