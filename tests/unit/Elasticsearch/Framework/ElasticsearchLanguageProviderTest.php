<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchLanguageProvider::class)]
class ElasticsearchLanguageProviderTest extends TestCase
{
    public function testGetLanguages(): void
    {
        $languageRepository = $this->createMock(EntityRepository::class);

        $languageRepository
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria) {
                static::assertTrue($criteria->hasEqualsFilter('fooo'));
                $sortings = $criteria->getSorting();
                static::assertCount(1, $sortings);
                static::assertEquals('id', $sortings[0]->getField());

                return new EntitySearchResult('foo', 0, new LanguageCollection(), null, $criteria, Context::createDefaultContext());
            });

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ElasticsearchIndexerLanguageCriteriaEvent::class, function (ElasticsearchIndexerLanguageCriteriaEvent $event): void {
            $event->getCriteria()->addFilter(new EqualsFilter('fooo', null));
        });

        $provider = new ElasticsearchLanguageProvider(
            $languageRepository,
            $dispatcher
        );

        $provider->getLanguages(
            Context::createDefaultContext()
        );
    }
}
