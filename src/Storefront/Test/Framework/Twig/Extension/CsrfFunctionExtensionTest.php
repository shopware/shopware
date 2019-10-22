<?php declare(strict_types=1);

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Shopware\Storefront\Framework\Twig\Extension\CsrfFunctionExtension;

class CsrfFunctionExtensionTest extends \PHPUnit\Framework\TestCase
{
    use IntegrationTestBehaviour;

    public function testCreatePlaceholderInputMode(): void
    {
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = sprintf(
            '<input type="hidden" name="_csrf_token" value="%stest#">',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER
        );
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'input']));
    }

    public function testCreatePlaceholderTokenMode(): void
    {
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = sprintf(
            '%stest#',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER
        );
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'token']));
    }

    public function testCreatePlaceholderWithoutMode(): void
    {
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = sprintf(
            '<input type="hidden" name="_csrf_token" value="%stest#">',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER
        );
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'input']));
    }
}
