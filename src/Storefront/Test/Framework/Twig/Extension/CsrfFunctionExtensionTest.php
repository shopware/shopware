<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Shopware\Storefront\Framework\Twig\Extension\CsrfFunctionExtension;

/**
 * @internal
 */
class CsrfFunctionExtensionTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreatePlaceholderInputMode(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = sprintf(
            '<input type="hidden" name="_csrf_token" value="%stest#">',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER
        );
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'input']));
    }

    public function testCreatePlaceholderTokenMode(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = sprintf(
            '%stest#',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER
        );
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'token']));
    }

    public function testCreatePlaceholderWithoutMode(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = sprintf(
            '<input type="hidden" name="_csrf_token" value="%stest#">',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER
        );
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'input']));
    }
}
