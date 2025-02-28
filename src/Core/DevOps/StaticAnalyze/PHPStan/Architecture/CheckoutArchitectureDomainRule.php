<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;

#[Package('checkout')]
class CheckoutArchitectureDomainRule
{
    private const DOMAINS = [
        'Cart',
        'Customer',
        'Document',
        'Order',
        'Payment',
        'Promotion',
        'Shipping',
    ];

    /**
     * @return iterable<Rule>
     */
    public function testDomainSeparationInCheckout(): iterable
    {
        return [];
        foreach (self::DOMAINS as $domain) {
            $realDomain = \sprintf('Shopware\Core\Checkout\%s', $domain);

            yield PHPat::rule()
                ->classes(Selector::inNamespace($realDomain))

                // exclude entities during collection
                ->excluding(
                    Selector::extends(Entity::class),
                    Selector::extends(EntityDefinition::class),
                    Selector::extends(EntityCollection::class),
                )
                ->canOnlyDependOn()
                ->classes(
                    Selector::inNamespace($realDomain),
                    Selector::NOT(Selector::inNamespace('Shopware')),

                    // some core classes are allowed
                    Selector::classname(Defaults::class),
                    Selector::classname(PlatformRequest::class),
                    Selector::inNamespace('Shopware\Core\Framework'),
                    Selector::inNamespace('Shopware\Core\System'),
                );
        }
    }
}
