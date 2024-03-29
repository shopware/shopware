<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\Api\MailActionController;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(MailActionController::class)]
class MailActionControllerTest extends TestCase
{
    private AbstractMailService&MockObject $mailService;

    private StringTemplateRenderer&MockObject $stringTemplateRenderer;

    protected function setUp(): void
    {
        $this->stringTemplateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->mailService = $this->createMock(AbstractMailService::class);
    }

    public function testSendSuccess(): void
    {
        $data = new RequestDataBag([
            'id' => 'random',
            'mailTemplateData' => [
                'order' => [
                    'id' => Uuid::randomHex(),
                ],
            ],
            'documentIds' => ['1'],
        ]);

        $this->mailService->expects(static::once())
            ->method('send')
            ->with(
                static::callback(function (array $data) {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);

                    return true;
                }),
                static::anything(),
                static::anything()
            );

        $mailActionController = new MailActionController(
            $this->mailService,
            $this->stringTemplateRenderer
        );

        $mailActionController->send($data, Context::createDefaultContext());
    }
}
