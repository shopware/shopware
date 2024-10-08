<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

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
        $flowVariables = new \ReflectionClass(FlowMailVariables::class);
        $message = 'The variable value is a public api for mail templates, you cant change it';

        static::assertSame('url', $flowVariables->getConstant('URL'), $message);
        static::assertSame('templateData', $flowVariables->getConstant('TEMPLATE_DATA'), $message);
        static::assertSame('subject', $flowVariables->getConstant('SUBJECT'), $message);
        static::assertSame('shopName', $flowVariables->getConstant('SHOP_NAME'), $message);
        static::assertSame('reviewFormData', $flowVariables->getConstant('REVIEW_FORM_DATA'), $message);
        static::assertSame('resetUrl', $flowVariables->getConstant('RESET_URL'), $message);
        static::assertSame('recipients', $flowVariables->getConstant('RECIPIENTS'), $message);
        static::assertSame('name', $flowVariables->getConstant('EVENT_NAME'), $message);
        static::assertSame('mediaId', $flowVariables->getConstant('MEDIA_ID'), $message);
        static::assertSame('email', $flowVariables->getConstant('EMAIL'), $message);
        static::assertSame('contactFormData', $flowVariables->getConstant('CONTACT_FORM_DATA'), $message);
        static::assertSame('contents', $flowVariables->getConstant('CONTENTS'), $message);
        static::assertSame('contextToken', $flowVariables->getConstant('CONTEXT_TOKEN'), $message);
        static::assertSame('confirmUrl', $flowVariables->getConstant('CONFIRM_URL'), $message);
        static::assertSame('data', $flowVariables->getConstant('DATA'), $message);
    }
}
