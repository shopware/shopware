<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class RestrictNamespacesRule
{
    private const NAMESPACE_ADMINISTRATION = 'Shopware\Administration';
    private const NAMESPACE_CORE = 'Shopware\Core';
    private const NAMESPACE_ELASTICSEARCH = 'Shopware\Elasticsearch';
    private const NAMESPACE_STOREFRONT = 'Shopware\Storefront';

    #[TestRule]
    public function restrictNamespacesInAdministration(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ADMINISTRATION))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
                Selector::inNamespace(self::NAMESPACE_STOREFRONT),
            );
    }

    #[TestRule]
    public function restrictNamespacesInCore(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_CORE))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
                Selector::inNamespace(self::NAMESPACE_STOREFRONT),
            );
    }

    #[TestRule]
    public function restrictNamespacesInElasticsearch(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_STOREFRONT),
            );
    }

    #[TestRule]
    public function restrictNamespacesInStorefront(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_STOREFRONT))
            ->shouldNotDependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
            );
    }
}
