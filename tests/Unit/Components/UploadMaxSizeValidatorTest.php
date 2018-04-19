<?php
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

namespace Shopware\Tests\Unit\Components;

use PHPUnit\Framework\TestCase;
use Shopware\Components\UploadMaxSizeException;
use Shopware\Components\UploadMaxSizeValidator;

class UploadMaxSizeValidatorTest extends TestCase
{
    /**
     * @var UploadMaxSizeValidator
     */
    private $SUT;

    protected function setUp()
    {
        $this->SUT = new UploadMaxSizeValidator();
    }

    public function testEmptyContentLength()
    {
        $eventArgs = $this->getMockEnlightControllerEventArgs();

        $this->SUT->validateContentLength($eventArgs);

        $this->assertTrue(true, 'Empty Content-Length should not throw an Exception');
    }

    public function testContentLengthInRange()
    {
        $testLength = $this->SUT->getPostMaxSize() / 2;
        $eventArgs = $this->getMockEnlightControllerEventArgs($testLength);

        $this->SUT->validateContentLength($eventArgs);

        $this->assertTrue(true, 'In range Content-Length should not throw an Exception');
    }

    public function testExceededContentLength()
    {
        $testLength = $this->SUT->getPostMaxSize() * 2;
        $eventArgs = $this->getMockEnlightControllerEventArgs($testLength);

        $this->expectException(UploadMaxSizeException::class);
        $this->expectExceptionCode(413);
        $this->expectExceptionMessage('The uploaded file was too large. Please try to upload a smaller file.');

        $this->SUT->validateContentLength($eventArgs);
    }

    /**
     * @param int $contentLength
     *
     * @return \Enlight_Controller_EventArgs
     */
    private function getMockEnlightControllerEventArgs($contentLength = 0)
    {
        $response = new \Enlight_Controller_Response_ResponseTestCase();
        $request = new \Enlight_Controller_Request_RequestTestCase();
        $request->setServer('CONTENT_LENGTH', $contentLength);
        $request->setMethod('POST');

        return new \Enlight_Controller_EventArgs([
            'request' => $request,
            'response' => $response,
        ]);
    }
}
