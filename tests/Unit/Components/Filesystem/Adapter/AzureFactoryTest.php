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

namespace Shopware\Tests\Unit\Components\Filesystem\Adapter;

use League\Flysystem\Azure\AzureAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Components\Filesystem\Adapter\AzureFactory;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class AzureFactoryTest extends TestCase
{
    /**
     * @var AzureFactory
     */
    private $factory;

    public function setUp()
    {
        $this->factory = new AzureFactory();
    }

    public function testTypeLocal()
    {
        $this->assertSame('microsoft-azure', $this->factory->getType());
    }

    public function testCreationWithEmptyConfig()
    {
        $this->expectException(MissingOptionsException::class);

        $this->factory->create([]);
    }

    public function testCreationWithValidConfig()
    {
        $filesystem = $this->factory->create([
            'container' => 'foobar',
            'accountName' => 'random-account-name',
            'apiKey' => base64_encode('random-api-key'),
        ]);

        $this->assertInstanceOf(AzureAdapter::class, $filesystem);
    }
}
