<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Padaliyajay\PHPAutoprefixer\Autoprefixer as PadaliyajayAutoprefixer;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;

/*
 * This class implements a pull request of the core autoprefixer, to enable
 * minification of the compiled CSS, if the update is released this
 * class should be removed and Padaliyajay\PHPAutoprefixer\Autoprefixer should
 * be used instead, see:
 * https://github.com/padaliyajay/php-autoprefixer/pull/8
 * https://github.com/padaliyajay/php-autoprefixer/issues/10
 */
class Autoprefixer extends PadaliyajayAutoprefixer
{
    /**
     * @var Parser
     */
    private $cssParser;

    public function __construct(string $cssCode)
    {
        $this->cssParser = new Parser($cssCode);
    }

    public function compile(bool $prettyOutput = true): string
    {
        $cssDocument = $this->cssParser->parse();

        $this->compileCSSList($cssDocument);

        $outputFormat = $prettyOutput
            ? OutputFormat::createPretty()
            : OutputFormat::createCompact();

        return $cssDocument->render($outputFormat);
    }
}
