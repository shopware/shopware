<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Document\Generator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Generator\PdfGenerator;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class PdfGeneratorTest extends TestCase
{
    use KernelTestBehaviour;

    public function testGenerate()
    {
        $pdfGenerator = $this->getContainer()->get(PdfGenerator::class);
        $template = file_get_contents('template.twig.html');
        $start = microtime(true);
        file_put_contents('/tmp/test.pdf', $pdfGenerator->generate($template));
        $total = microtime(true) - $start;
        echo sprintf('Execution of "" took %f', $total);
    }
}
