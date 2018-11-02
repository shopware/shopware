<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search\Util;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;

interface KeywordSearchTermInterpreterInterface
{
    public function interpret(string $word, string $scope, Context $context): SearchPattern;
}
