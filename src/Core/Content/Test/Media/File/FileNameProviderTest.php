<?php declare(strict_types=1);

namespace src\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FileNameProviderTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFixtures;

    /**
     * @var FileNameProvider
     */
    private $nameProvider;

    /**
     * @var Context
     */
    private $context;

    public function setUp()
    {
        $this->nameProvider = $this->getContainer()->get(FileNameProvider::class);

        $this->context = Context::createDefaultContext();
        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->setFixtureContext($this->context);
    }

    public function testItReturnsOriginalName()
    {
        $media = $this->getEmptyMedia();
        $original = 'test';

        $new = $this->nameProvider->provide($original, 'png', $media->getId(), $this->context);

        static::assertEquals($original, $new);
    }

    public function testItGeneratesNewName()
    {
        $existing = $this->getJpg();

        $media = $this->getEmptyMedia();

        $new = $this->nameProvider->provide(
            $existing->getFileName(),
            $existing->getFileExtension(),
            $media->getId(),
            $this->context
        );

        self::assertEquals($existing->getFileName() . '_(1)', $new);
    }

    public function testItReturnsOriginalNameOnNewExtension()
    {
        $existing = $this->getJpg();

        $media = $this->getEmptyMedia();

        $new = $this->nameProvider->provide(
            $existing->getFileName(),
            'png',
            $media->getId(),
            $this->context
        );

        static::assertEquals($existing->getFileName(), $new);
    }

    public function testItGeneratesNewNameWithoutMediaEntity()
    {
        $existing = $this->getJpg();

        $new = $this->nameProvider->provide(
            $existing->getFileName(),
            $existing->getFileExtension(),
            null,
            $this->context
        );

        self::assertEquals($existing->getFileName() . '_(1)', $new);
    }
}
