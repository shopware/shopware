<?php declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->load(__DIR__.'/../.env');

use Shopware\Framework\Write\Generator;

class GenerateWriter
{
    const UUID = 'AA-BB-CC';

    /**
     * @var AppKernel
     */
    private $kernel;

    /**
     * GenerateWriter constructor.
     *
     * @param AppKernel $kernel
     */
    public function __construct(AppKernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function run()
    {
        $connection = $this->kernel->getContainer()->get('dbal_connection');
        $connection->transactional(function() {
            (new \Shopware\Framework\Write\Generator($this->kernel->getContainer()))->generateAll();
        });
    }
}

$kernel = new AppKernel('dev', true);
$kernel->boot(false);

$generator = new GenerateWriter($kernel);
$generator->run();