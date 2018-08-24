<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Extension;

use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Media\Extension\MediaLinksStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UrlExtensionTest extends KernelTestCase
{
    public function testExtensionSubscribesToMediaLoaded()
    {
        self::bootKernel();
        $urlExtension = self::$container->get('Shopware\Core\Content\Media\Extension\UrlExtension');
        static::assertEquals(['media.loaded' => 'mediaLoaded'], $urlExtension->getSubscribedEvents());
    }

    public function testExtensionWorksOnMedia()
    {
        self::bootKernel();
        $urlExtension = self::$container->get('Shopware\Core\Content\Media\Extension\UrlExtension');
        static::assertEquals(MediaDefinition::class, $urlExtension->getDefinitionClass());
    }

    public function testExtensionAddsUrl()
    {
        self::bootKernel();
        $urlExtension = self::$container->get('Shopware\Core\Content\Media\Extension\UrlExtension');
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $mediaId = Uuid::uuid4()->getHex();
        $mimeType = 'image/png';

        $mediaStruct = new MediaStruct();
        $mediaStruct->setId($mediaId);
        $mediaStruct->setMimeType($mimeType);
        $mediaStruct->setFileExtension('png');

        $mediaLoadedEvent = new EntityLoadedEvent(MediaDefinition::class, new EntityCollection([$mediaStruct]), $context);
        $urlExtension->mediaLoaded($mediaLoadedEvent);

        //find /media{path}mediaId.extension
        $searchPattern = '/\/media(\/.+)*\/' . $mediaId . '.png' . '/';

        static::assertEquals(MediaLinksStruct::class, get_class($mediaStruct->getExtension('links')));
        static::assertTrue(boolval(preg_match($searchPattern, $mediaStruct->getExtension('links')->getUrl())));
    }

    public function testExtensionIsNotAddedForUnknownMimeType()
    {
        self::bootKernel();
        $urlExtension = self::$container->get('Shopware\Core\Content\Media\Extension\UrlExtension');
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $mediaId = Uuid::uuid4()->getHex();
        $mimeType = 'image/asdcwas';

        $mediaStruct = new MediaStruct();
        $mediaStruct->setId($mediaId);
        $mediaStruct->setMimeType($mimeType);

        $mediaLoadedEvent = new EntityLoadedEvent(MediaDefinition::class, new EntityCollection([$mediaStruct]), $context);
        $urlExtension->mediaLoaded($mediaLoadedEvent);

        static::assertNull($mediaStruct->getExtension('links'));
    }
}
