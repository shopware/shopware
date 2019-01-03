<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreatePageletCommand extends Command
{
    /**
     * @var string
     */
    private $projectDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    protected function configure()
    {
        $this->setName('platform:create-pagelet')
            ->addArgument('namespace', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Creating pagelet...');
        $directory = $this->projectDir . '/vendor/shopware/platform/src/Storefront/';
        $namespace = (string) $input->getArgument('namespace');
        $name = (string) $input->getArgument('name');

        if (!$namespace) {
            throw new InvalidArgumentException('Please specify namespace.');
        }

        // Both dir and namespace were given
        if ($directory) {
            $this->createFiles($name, $output, realpath($directory), $namespace);
            $output->writeln('Creating pagelet Done!');

            return;
        }
    }

    protected function createFiles(string $name, OutputInterface $output, string $directory, string $namespace): void
    {
        if (!preg_match('/^[a-zA-Z0-9\_]*$/', $name)) {
            throw new InvalidArgumentException('Pageletname contains forbidden characters!');
        }

        if (!preg_match('/^[a-zA-Z0-9\_]*$/', $namespace)) {
            throw new InvalidArgumentException('Namespace contains forbidden characters!');
        }

        $name = ucfirst($name);
        $namespace = ucfirst($namespace);

        $path = rtrim($directory, '/') . '/Pagelet/' . $namespace . $name;
        $files = [
            'RequestEvent' => '/' . $namespace . $name . 'PageletRequestEvent.php',
            'Request' => '/' . $namespace . $name . 'PageletRequest.php',
            'RequestResolver' => '/' . $namespace . $name . 'PageletRequestResolver.php',
            'Struct' => '/' . $namespace . $name . 'PageletStruct.php',
            'Loader' => '/' . $namespace . $name . 'PageletLoader.php',
            'Subscriber' => '/' . $namespace . $name . 'PageletSubscriber.php',
        ];

        foreach ($files as $type => $filename) {
            if (file_exists($path . $filename)) {
                $output->writeln($filename . ' already axists -> will not be overwritten');
                continue;
            }
            $file = fopen($path . $filename, 'w');
            $template = $this->getTemplate($type);
            $params = [
                '%%namespace%%' => $namespace,
                '%%namespaceLower%%' => strtolower($namespace),
                '%%namespaceUPPER%%' => strtoupper($namespace),
                '%%name%%' => $name,
                '%%nameLower%%' => strtolower($name),
                '%%nameUPPER%%' => strtoupper($name),
            ];
            fwrite($file, str_replace(array_keys($params), array_values($params), $template));
            fclose($file);
            $output->writeln($filename . ' created!');
        }

        $output->writeln('Pagelet-Files created: "' . $namespace . ':' . $name . '"');
    }

    private function getTemplate($type)
    {
        switch ($type) {
            case 'RequestEvent':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\%%namespace%%%%name%%;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Storefront\Pagelet\%%namespace%%%%name%%\%%namespace%%%%name%%PageletRequest;
use Symfony\Component\HttpFoundation\Request;

class %%namespace%%%%name%%PageletRequestEvent extends NestedEvent
{
    public const NAME = '%%namespaceLower%%.%%nameLower%%.pagelet.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var %%namespace%%%%name%%PageletRequest
     */
    protected $%%namespaceLower%%%%name%%PageletRequest;

    public function __construct(Request $request, CheckoutContext $context, %%namespace%%%%name%%PageletRequest $%%namespaceLower%%%%name%%PageletRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->%%namespaceLower%%%%name%%PageletRequest = $%%namespaceLower%%%%name%%PageletRequest;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function get%%namespace%%%%name%%PageletRequest(): %%namespace%%%%name%%PageletRequest
    {
        return $this->%%namespaceLower%%%%name%%PageletRequest;
    }
}


EOD;
                break;
            case 'Request':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\%%namespace%%%%name%%;

class %%namespace%%%%name%%PageletRequest
{

}

EOD;
                break;
            case 'RequestResolver':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\%%namespace%%%%name%%;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Pagelet\%%namespace%%%%name%%\%%namespace%%%%name%%PageletRequestEvent;
use Shopware\Storefront\Framework\Page\PageRequestResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class %%namespace%%%%name%%PageletRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === %%namespace%%%%name%%PageletRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $%%namespaceLower%%%%name%%PageletRequest = new %%namespace%%%%name%%PageletRequest();

        $event = new %%namespace%%%%name%%PageletRequestEvent($request, $context, $%%namespaceLower%%%%name%%PageletRequest);
        $this->eventDispatcher->dispatch(%%name%%PageletRequestEvent::NAME, $event);

        yield $event->get%%namespace%%%%name%%PageletRequest();
    }
}

EOD;
                break;
            case 'Loader':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\%%namespace%%%%name%%;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Page\%%namespace%%%%name%%\%%namespace%%%%name%%PageletStruct;
use Shopware\Storefront\Page\%%namespace%%%%name%%\%%namespace%%%%name%%PageletRequest;

use Symfony\Component\DependencyInjection\ContainerInterface;

class %%namespace%%%%name%%PageletLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct()
    {
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param %%namespace%%%%name%%PageletRequest   $request
     * @param CheckoutContext                       $context
     *
     * @return %%namespace%%%%name%%PageletStruct
     */
    public function load(%%namespace%%PageletRequest $request, CheckoutContext $context): %%namespace%%%%name%%PageletStruct
    {
        return new %%namespace%%%%name%%PageletStruct();
    }
}


EOD;
                break;
            case 'Subscriber':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\%%namespace%%%%name%%;

use Shopware\Storefront\%%namespace%%%%name%%\%%namespace%%%%name%%PageletRequestEvent;
use Shopware\Storefront\Event\%%namespace%%Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class %%namespace%%%%name%%PageletSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            %%namespace%%Events::%%namespaceUPPER%%_%%nameUPPER%%_PAGELET_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(%%name%%PageletRequestEvent $event): void
    {
        $%%namespaceLower%%%%name%%PageletRequest = $event->get%%namespace%%%%name%%PageletRequest();
    }
}


EOD;
                break;
            case 'Struct':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\%%namespace%%%%name%%;

use Shopware\Storefront\Framework\Page\PageletStruct;

class %%namespace%%%%name%%PageletStruct extends PageletStruct
{
    
}


EOD;
        }
    }
}
