<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Robots\Parser;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore Simple enum with no logic - covered by ParseIssue and ParsedRobots tests
 */
#[Package('framework')]
enum ParseIssueSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
}
