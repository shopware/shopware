<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * Header and footer are Storefront tables, so {@see CacheInvalidationSubscriber} learns their names from a
 * container parameter the Storefront fills. Core declares the parameter empty, which only holds while the
 * Storefront bundle is loaded after the Framework bundle.
 *
 * @internal
 */
#[Package('framework')]
class SectionAssignmentWiringTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the storefront contributes the header and footer assignment tables to cache invalidation')]
    public function testStorefrontContributesSectionAssignments(): void
    {
        static::assertSame(
            [
                HeaderContentLayoutDefinition::ENTITY_NAME => ContentSection::HEADER->value,
                FooterContentLayoutDefinition::ENTITY_NAME => ContentSection::FOOTER->value,
            ],
            static::getContainer()->getParameter('shopware.content_system.section_assignment_entities'),
        );
    }
}
