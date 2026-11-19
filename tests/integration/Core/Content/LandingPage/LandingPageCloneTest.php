<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\LandingPage;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the clone payload of the Administration's duplicate action in sw-landing-page-tree.
 *
 * @internal
 */
#[Package('discovery')]
class LandingPageCloneTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testDuplicateLandingPageAppliesOverwritesAndKeepsAssignments(): void
    {
        $context = Context::createDefaultContext();
        $landingPageId = Uuid::randomHex();
        $cmsPageId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        /** @var EntityRepository<LandingPageCollection> $landingPageRepository */
        $landingPageRepository = static::getContainer()->get('landing_page.repository');
        $landingPageRepository->create([[
            'id' => $landingPageId,
            'name' => 'Summer sale',
            'url' => 'summer-sale',
            'active' => true,
            'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]],
            'tags' => [['id' => $tagId, 'name' => 'Campaign']],
            'cmsPage' => ['id' => $cmsPageId, 'type' => 'landingpage'],
        ]], $context);

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/_action/clone/landing-page/' . $landingPageId, [
            'cloneChildren' => false,
            'overwrites' => [
                'name' => 'Summer sale Copy',
                'url' => 'summer-sale-Copy',
                'active' => false,
            ],
        ]);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        static::assertIsString($body['id']);
        static::assertNotSame($landingPageId, $body['id']);

        $criteria = new Criteria([$landingPageId, $body['id']]);
        $criteria->addAssociations(['salesChannels', 'tags']);
        $landingPages = $landingPageRepository->search($criteria, $context)->getEntities();

        $clone = $landingPages->get($body['id']);
        static::assertInstanceOf(LandingPageEntity::class, $clone);
        static::assertSame('Summer sale Copy', $clone->getName());
        static::assertSame('summer-sale-Copy', $clone->getUrl());
        static::assertFalse($clone->isActive());
        static::assertSame($cmsPageId, $clone->getCmsPageId());
        static::assertSame([TestDefaults::SALES_CHANNEL], array_values($clone->getSalesChannels()?->getIds() ?? []));
        static::assertSame([$tagId], array_values($clone->getTags()?->getIds() ?? []));

        $source = $landingPages->get($landingPageId);
        static::assertInstanceOf(LandingPageEntity::class, $source);
        static::assertSame('Summer sale', $source->getName());
        static::assertSame('summer-sale', $source->getUrl());
        static::assertTrue($source->isActive());
        static::assertSame([TestDefaults::SALES_CHANNEL], array_values($source->getSalesChannels()?->getIds() ?? []));
        static::assertSame([$tagId], array_values($source->getTags()?->getIds() ?? []));
    }
}
