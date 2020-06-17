<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class FilesystemConfigMigrationCompilerPassTest extends TestCase
{
    /**
     * @var ContainerBuilder
     */
    private $builder;

    public function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->builder->addCompilerPass(new FilesystemConfigMigrationCompilerPass());
        $this->builder->setParameter('shopware.filesystem.public', []);
        $this->builder->setParameter('shopware.filesystem.public.type', 'local');
        $this->builder->setParameter('shopware.filesystem.public.config', []);
        $this->builder->setParameter('shopware.cdn.url', 'http://test.de');
    }

    public function testConfigMigration(): void
    {
        $this->builder->compile();

        static::assertSame($this->builder->getParameter('shopware.filesystem.public'), $this->builder->getParameter('shopware.filesystem.theme'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public'), $this->builder->getParameter('shopware.filesystem.asset'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public'), $this->builder->getParameter('shopware.filesystem.sitemap'));

        static::assertSame($this->builder->getParameter('shopware.filesystem.public.type'), $this->builder->getParameter('shopware.filesystem.theme.type'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.type'), $this->builder->getParameter('shopware.filesystem.asset.type'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.type'), $this->builder->getParameter('shopware.filesystem.sitemap.type'));

        static::assertSame($this->builder->getParameter('shopware.filesystem.public.config'), $this->builder->getParameter('shopware.filesystem.theme.config'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.config'), $this->builder->getParameter('shopware.filesystem.asset.config'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.config'), $this->builder->getParameter('shopware.filesystem.sitemap.config'));

        // We cannot inherit them, cause they use always in 6.2 the shop url instead the configured one
        static::assertSame('', $this->builder->getParameter('shopware.filesystem.theme.url'));
        static::assertSame('', $this->builder->getParameter('shopware.filesystem.asset.url'));
        static::assertSame('', $this->builder->getParameter('shopware.filesystem.sitemap.url'));
    }

    public function testSetCustomConfigForTheme(): void
    {
        $this->builder->setParameter('shopware.filesystem.theme', ['foo' => 'foo']);
        $this->builder->setParameter('shopware.filesystem.theme.type', 'amazon-s3');
        $this->builder->setParameter('shopware.filesystem.theme.config', ['test' => 'test']);
        $this->builder->setParameter('shopware.filesystem.theme.url', 'http://cdn.de');

        $this->builder->compile();

        static::assertNotSame($this->builder->getParameter('shopware.filesystem.public'), $this->builder->getParameter('shopware.filesystem.theme'));
        static::assertNotSame($this->builder->getParameter('shopware.filesystem.public.type'), $this->builder->getParameter('shopware.filesystem.theme.type'));
        static::assertNotSame($this->builder->getParameter('shopware.filesystem.public.config'), $this->builder->getParameter('shopware.filesystem.theme.config'));

        static::assertSame('amazon-s3', $this->builder->getParameter('shopware.filesystem.theme.type'));
        static::assertSame('http://cdn.de', $this->builder->getParameter('shopware.filesystem.theme.url'));
        static::assertSame(['test' => 'test'], $this->builder->getParameter('shopware.filesystem.theme.config'));

        static::assertSame($this->builder->getParameter('shopware.filesystem.public'), $this->builder->getParameter('shopware.filesystem.asset'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.type'), $this->builder->getParameter('shopware.filesystem.asset.type'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.config'), $this->builder->getParameter('shopware.filesystem.asset.config'));

        static::assertSame($this->builder->getParameter('shopware.filesystem.public'), $this->builder->getParameter('shopware.filesystem.sitemap'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.type'), $this->builder->getParameter('shopware.filesystem.sitemap.type'));
        static::assertSame($this->builder->getParameter('shopware.filesystem.public.config'), $this->builder->getParameter('shopware.filesystem.sitemap.config'));
    }
}
