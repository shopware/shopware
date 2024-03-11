<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;

/**
 * @internal
 */
#[CoversClass(FlowMailVariables::class)]
class FlowMailVariablesTest extends TestCase
{
    public function testVariablesAreStillTheSame(): void
    {
        static::assertSame(FlowMailVariables::URL, 'url', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::TEMPLATE_DATA, 'templateData', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::SUBJECT, 'subject', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::SHOP_NAME, 'shopName', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::REVIEW_FORM_DATA, 'reviewFormData', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::RESET_URL, 'resetUrl', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::RECIPIENTS, 'recipients', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::EVENT_NAME, 'name', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::MEDIA_ID, 'mediaId', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::EMAIL, 'email', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::CONTACT_FORM_DATA, 'contactFormData', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::CONTENTS, 'contents', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::CONTEXT_TOKEN, 'contextToken', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::CONFIRM_URL, 'confirmUrl', 'The variable value is a public api for mail templates, you cant change it');
        static::assertSame(FlowMailVariables::DATA, 'data', 'The variable value is a public api for mail templates, you cant change it');
    }
}
