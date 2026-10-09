<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowMailVariables::class)]
class FlowMailVariablesTest extends TestCase
{
    #[DataProvider('provideVariables')]
    public function testVariablesAreStillTheSame(string $expected, string $actual): void
    {
        static::assertSame($expected, $actual, 'The variable value is a public api for mail templates, you cant change it');
    }

    public static function provideVariables(): \Generator
    {
        yield 'URL' => ['url', FlowMailVariables::URL];
        yield 'TEMPLATE_DATA' => ['templateData', FlowMailVariables::TEMPLATE_DATA];
        yield 'SUBJECT' => ['subject', FlowMailVariables::SUBJECT];
        yield 'SHOP_NAME' => ['shopName', FlowMailVariables::SHOP_NAME];
        yield 'REVIEW_FORM_DATA' => ['reviewFormData', FlowMailVariables::REVIEW_FORM_DATA];
        yield 'RESET_URL' => ['resetUrl', FlowMailVariables::RESET_URL];
        yield 'RECIPIENTS' => ['recipients', FlowMailVariables::RECIPIENTS];
        yield 'EVENT_NAME' => ['name', FlowMailVariables::EVENT_NAME];
        yield 'MEDIA_ID' => ['mediaId', FlowMailVariables::MEDIA_ID];
        yield 'EMAIL' => ['email', FlowMailVariables::EMAIL];
        yield 'REVOCATION_REQUEST_FORM_DATA' => ['revocationRequestFormData', FlowMailVariables::REVOCATION_REQUEST_FORM_DATA];
        yield 'CONTACT_FORM_DATA' => ['contactFormData', FlowMailVariables::CONTACT_FORM_DATA];
        yield 'CONTENTS' => ['contents', FlowMailVariables::CONTENTS];
        yield 'CONTEXT_TOKEN' => ['contextToken', FlowMailVariables::CONTEXT_TOKEN];
        yield 'CONFIRM_URL' => ['confirmUrl', FlowMailVariables::CONFIRM_URL];
        yield 'DATA' => ['data', FlowMailVariables::DATA];
    }
}
