<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->load(__DIR__.'/../.env');

class GenerateWriter
{
    public function run()
    {
        $generator = new \Shopware\Framework\Write\Generator();
        $generator->generateAll();
    }
}

$generator = new GenerateWriter();
$generator->run();