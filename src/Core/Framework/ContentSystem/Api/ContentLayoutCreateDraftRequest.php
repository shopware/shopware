<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of the create-draft action. Without `fromRevisionId` the draft starts at the live revision.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ContentLayoutCreateDraftRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public ?string $name = null,
        public ?string $fromRevisionId = null,
    ) {
    }
}
