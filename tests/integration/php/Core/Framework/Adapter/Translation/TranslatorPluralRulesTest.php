<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Translation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Translation\Translator
 */
class TranslatorPluralRulesTest extends TestCase
{
    use KernelTestBehaviour;

    public function getTranslator(): TranslatorInterface
    {
        return $this->getContainer()->get('translator');
    }

    /**
     * @dataProvider getChooseTests
     */
    public function testPluralRules($expected, $id, $number, $locale = null)
    {
        $this->assertEquals($expected, $this->getTranslator()->trans($id, ['%count%' => $number], null, $locale));
    }

    public static function getChooseTests()
    {
        return [
            // Test English plural rules
            ['There are 0 apples', 'There is one apple|There are %count% apples', 0, 'en-GB'],
            ['There is one apple', 'There is one apple|There are %count% apples', 1, 'en-GB'],
            ['There are 2 apples', 'There is one apple|There are %count% apples', 2, 'en-GB'],
            ['There are 21 apples', 'There is one apple|There are %count% apples', 21, 'en-GB'],

            ['There are 0 apples', 'There is one apple|There are %count% apples', 0, 'en_GB'],
            ['There is one apple', 'There is one apple|There are %count% apples', 1, 'en_GB'],
            ['There are 2 apples', 'There is one apple|There are %count% apples', 2, 'en_GB'],
            ['There are 21 apples', 'There is one apple|There are %count% apples', 21, 'en_GB'],

            // Test Ukrainian plural rules
            ['0 яблук', '%count% яблуко|%count% яблука|%count% яблук', 0, 'uk-UA'],
            ['1 яблуко', '%count% яблуко|%count% яблука|%count% яблук', 1, 'uk-UA'],
            ['2 яблука', '%count% яблуко|%count% яблука|%count% яблук', 2, 'uk-UA'],
            ['5 яблук', '%count% яблуко|%count% яблука|%count% яблук', 5, 'uk-UA'],
            ['21 яблуко', '%count% яблуко|%count% яблука|%count% яблук', 21, 'uk-UA'],

            ['0 яблук', '%count% яблуко|%count% яблука|%count% яблук', 0, 'uk_UA'],
            ['1 яблуко', '%count% яблуко|%count% яблука|%count% яблук', 1, 'uk_UA'],
            ['2 яблука', '%count% яблуко|%count% яблука|%count% яблук', 2, 'uk_UA'],
            ['5 яблук', '%count% яблуко|%count% яблука|%count% яблук', 5, 'uk_UA'],
            ['21 яблуко', '%count% яблуко|%count% яблука|%count% яблук', 21, 'uk_UA'],
        ];
    }
}
