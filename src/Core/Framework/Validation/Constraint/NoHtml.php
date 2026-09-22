<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Validation\Constraint;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NoHtml extends Constraint
{
    final public const CONTAINS_HTML_ERROR = 'CONTAINS_HTML_ERROR';

    protected const ERROR_NAMES = [
        self::CONTAINS_HTML_ERROR => 'CONTAINS_HTML_ERROR',
    ];

    #[HasNamedArguments]
    public function __construct(private readonly string $message = 'This value must not contain HTML.')
    {
        parent::__construct();
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
