<?php declare(strict_types=1);

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Framework\Twig\Extension\CsrfFunctionExtension;

class CsrfFunctionExtensionTest extends \PHPUnit\Framework\TestCase
{
    use IntegrationTestBehaviour;

    public function testCreatePlaceholderInputMode(): void
    {
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = '<!-- csrf.test mode.input -->';
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'input']));
    }

    public function testCreatePlaceholderTokenMode(): void
    {
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = '<!-- csrf.test mode.token -->';
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'token']));
    }

    public function testCreatePlaceholderWithoutMode(): void
    {
        $function = new CsrfFunctionExtension();
        $expectedPlaceholder = '<!-- csrf.test mode.input -->';
        static::assertEquals($expectedPlaceholder, $function->createCsrfPlaceholder('test', ['mode' => 'input']));
    }
}
