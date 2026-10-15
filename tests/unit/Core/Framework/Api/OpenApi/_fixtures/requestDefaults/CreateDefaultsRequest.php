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
    public const DEFAULT_ID = 'fallback';

    public const DEFAULT_COUNT = 10;

    public const DEFAULT_ACTIVE = true;

    public const DEFAULT_COMMENT = 'fallback';

    public const DEFAULT_TAGS = ['first', 'first', 'second'];

    public const DEFAULT_EMPTY_TAGS = [];

    public const DEFAULT_OPTIONS = ['mode' => 'fast', 'nested' => ['values' => [1, false, null]], 'label' => 'it\'s\\configured'];

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
     * @var list<string>
     */
    #[Assert\All(new Assert\Type('string'))]
    public array $tags;

    /**
     * @var list<string>
     */
    #[Assert\All(new Assert\Type('string'))]
    public array $emptyTags;

    /**
     * @var array<string, mixed>
     */
    public array $options;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public string $id,
    ) {
    }
}
