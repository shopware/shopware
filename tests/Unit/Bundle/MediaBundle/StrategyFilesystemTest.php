<?php

declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Tests\Unit\Components\Filesystem;

use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Shopware\Bundle\MediaBundle\Strategy\StrategyInterface;
use Shopware\Bundle\MediaBundle\StrategyFilesystem;

class StrategyFilesystemTest extends TestCase
{
    public function testPreparePath()
    {
        $path = 'media/image/foo.jpg';
        $md5Path = 'media/image/10/93/15/foo.jpg';

        $coreFilesystemProphecy = $this->prophesize(FilesystemInterface::class);
        $coreFilesystemProphecy->has($md5Path)->shouldBeCalled();

        $strategyProphecy = $this->prophesize(StrategyInterface::class);
        $strategyProphecy->encode(Argument::is($path))->willReturn($md5Path)->shouldBeCalled();

        $filesystem = new StrategyFilesystem($coreFilesystemProphecy->reveal(), $strategyProphecy->reveal());
        $filesystem->has($path);
    }

    public function testStripPath()
    {
        $md5Path = 'media/image/10/93/15/foo.jpg';
        $path = 'media/image/foo.jpg';

        $returnMetadata = [
            'path' => 'media/image/10/93/15/foo.jpg',
            'timestamp' => 1488375339,
            'dirname' => 'media/image/10/93/15',
            'mimetype' => 'application/octet-stream',
            'size' => 14,
            'type' => 'file',
        ];

        $expectedMetadata = [
            'path' => 'media/image/foo.jpg',
            'timestamp' => 1488375339,
            'dirname' => 'media/image/10/93/15',
            'mimetype' => 'application/octet-stream',
            'size' => 14,
            'type' => 'file',
        ];

        $coreFilesystemProphecy = $this->prophesize(FilesystemInterface::class);
        $coreFilesystemProphecy->getMetadata($md5Path)->willReturn($returnMetadata)->shouldBeCalled();

        $strategyProphecy = $this->prophesize(StrategyInterface::class);
        $strategyProphecy->encode($path)->willReturn($md5Path)->shouldBeCalled();
        $strategyProphecy->normalize($returnMetadata['path'])->willReturn($path)->shouldBeCalled();
        $strategyProphecy->normalize($returnMetadata['dirname'])->willReturn($returnMetadata['dirname'])->shouldBeCalled();

        $filesystem = new StrategyFilesystem($coreFilesystemProphecy->reveal(), $strategyProphecy->reveal());
        $meta = $filesystem->getMetadata($path);

        $this->assertSame($expectedMetadata, $meta);
    }
}
