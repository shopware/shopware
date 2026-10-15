<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace App\DTO;

use App\Shared\SharedDefaults;
use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CreateDefaultsRequest extends AbstractRequest
{
    public int $count;

    public bool $active;

    public ?string $comment;

    #[Assert\Choice(choices: ['api'])]
    public string $source;

    #[Assert\Valid]
    public CreateDefaultsRequestNested $nested;

    #[Assert\Valid]
    public SharedDefaults $shared;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $id,
    ) {
    }
}
