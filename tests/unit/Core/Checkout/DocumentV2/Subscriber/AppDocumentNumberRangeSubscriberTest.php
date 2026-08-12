<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\Subscriber\AppDocumentNumberRangeSubscriber;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(AppDocumentNumberRangeSubscriber::class)]
final class AppDocumentNumberRangeSubscriberTest extends TestCase
{
    public function testSubscribesToInstallAndUpdate(): void
    {
        static::assertSame(
            [
                AppInstalledEvent::class => 'onAppInstalledOrUpdated',
                AppUpdatedEvent::class => 'onAppInstalledOrUpdated',
            ],
            AppDocumentNumberRangeSubscriber::getSubscribedEvents()
        );
    }

    public function testSeedsANumberRangeForADeclaredAppType(): void
    {
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class, [[]]);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class);

        $subscriber = new AppDocumentNumberRangeSubscriber($typeRepository, $rangeRepository);
        $subscriber->onAppInstalledOrUpdated($this->event($this->manifestWithType('swag_warranty')));

        static::assertCount(1, $typeRepository->creates);

        $type = $typeRepository->creates[0][0];
        static::assertSame(DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . 'swag_warranty', $type['technicalName']);
        static::assertSame('swag_warranty', $type['typeName']);
        static::assertTrue($type['global']);

        static::assertCount(1, $rangeRepository->creates);

        $range = $rangeRepository->creates[0][0];
        static::assertSame($type['id'], $range['typeId']);
        static::assertSame('swag_warranty', $range['name']);
        static::assertSame('{n}', $range['pattern']);
        static::assertSame(1000, $range['start']);
        static::assertTrue($range['global']);
    }

    public function testSkipsWhenTheNumberRangeTypeAlreadyExists(): void
    {
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class, [[Uuid::randomHex()]]);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class);

        $subscriber = new AppDocumentNumberRangeSubscriber($typeRepository, $rangeRepository);
        $subscriber->onAppInstalledOrUpdated($this->event($this->manifestWithType('swag_warranty')));

        static::assertSame([], $typeRepository->creates);
        static::assertSame([], $rangeRepository->creates);
    }

    public function testDoesNotSeedARangeForACoreTypeIdentifier(): void
    {
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class);

        $subscriber = new AppDocumentNumberRangeSubscriber($typeRepository, $rangeRepository);
        $subscriber->onAppInstalledOrUpdated($this->event($this->manifestWithType('invoice')));

        static::assertSame([], $typeRepository->creates);
        static::assertSame([], $rangeRepository->creates);
    }

    public function testDoesNothingWhenTheManifestDeclaresNoDocuments(): void
    {
        $typeRepository = StaticEntityRepository::of(NumberRangeTypeCollection::class);
        $rangeRepository = StaticEntityRepository::of(NumberRangeCollection::class);

        $subscriber = new AppDocumentNumberRangeSubscriber($typeRepository, $rangeRepository);
        $subscriber->onAppInstalledOrUpdated($this->event($this->manifest('')));

        static::assertSame([], $typeRepository->creates);
        static::assertSame([], $rangeRepository->creates);
    }

    private function event(Manifest $manifest): AppInstalledEvent
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('DocumentApp');

        return new AppInstalledEvent($app, $manifest, Context::createDefaultContext());
    }

    private function manifestWithType(string $identifier): Manifest
    {
        return $this->manifest(
            <<<XML
                <documents>
                    <document-type>
                        <identifier>{$identifier}</identifier>
                        <label>Test</label>
                        <formats>
                            <format>html</format>
                        </formats>
                    </document-type>
                </documents>
            XML
        );
    }

    private function manifest(string $documents): Manifest
    {
        return Manifest::createFromXml(
            <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>DocumentApp</name>
                    <label>Document App</label>
                    <author>shopware AG</author>
                    <copyright>(c) shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                {$documents}
            </manifest>
            XML
        );
    }
}
