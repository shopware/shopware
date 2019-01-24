<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreatePageCommand extends Command
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
        $this->setName('platform:create-page')
            ->addArgument('namespace', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Creating page...');
        $directory = $this->projectDir . '/vendor/shopware/platform/src/Storefront/';
        $namespace = (string) $input->getArgument('namespace');
        $name = (string) $input->getArgument('name');

        if (!$namespace) {
            throw new InvalidArgumentException('Please specify namespace.');
        }

        // Both dir and namespace were given
        if ($directory) {
            $this->createFiles($name, $output, realpath($directory), $namespace);
            $output->writeln('Creating page Done!');

            return null;
        }
    }

    protected function createFiles(string $name, OutputInterface $output, string $directory, string $namespace): void
    {
        if (!preg_match('/^[a-zA-Z0-9\_]*$/', $name)) {
            throw new InvalidArgumentException('Pagename contains forbidden characters!');
        }

        if (!preg_match('/^[a-zA-Z0-9\_]*$/', $namespace)) {
            throw new InvalidArgumentException('Namespace contains forbidden characters!');
        }

        $name = ucfirst($name);
        $namespace = ucfirst($namespace);

        $path = rtrim($directory, '/') . '/Page/' . $namespace . $name;
        $files = [
            'LoadedEvent' => '/' . $namespace . $name . 'PageLoadedEvent.php',
            'RequestEvent' => '/' . $namespace . $name . 'PageRequestEvent.php',
            'Request' => '/' . $namespace . $name . 'PageRequest.php',
            'RequestResolver' => '/' . $namespace . $name . 'PageRequestResolver.php',
            'Struct' => '/' . $namespace . $name . 'PageStruct.php',
            'Loader' => '/' . $namespace . $name . 'PageLoader.php',
            'Subscriber' => '/' . $namespace . $name . 'PageSubscriber.php',
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

        $output->writeln('Page-Files created: "' . $namespace . '/' . $name . '"');
    }

    private function getTemplate($type)
    {
        switch ($type) {
            case 'LoadedEvent':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\%%namespace%%%%name%%\%%namespace%%%%name%%PageletLoadedEvent;
use Shopware\Storefront\Pagelet\ContentHeader\HeaderPageletLoadedEvent;

class %%namespace%%%%name%%PageLoadedEvent extends NestedEvent
{
    public const NAME = '%%namespaceLower%%-%%nameLower%%.page.loaded';

    /**
     * @var %%namespace%%%%name%%PageStruct
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var %%namespace%%%%name%%PageRequest
     */
    protected $request;

    public function __construct(%%namespace%%%%name%%PageStruct $page, CheckoutContext $context, %%namespace%%%%name%%PageRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new HeaderPageletLoadedEvent($this->page->getHeader(), $this->context, $this->request->getHeaderRequest()),
            new %%namespace%%%%name%%PageletLoadedEvent($this->page->get%%namespace%%%%name%%(), $this->context, $this->request->get%%namespace%%%%name%%Request())
        ]);
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

    public function getPage(): %%namespace%%%%name%%PageStruct
    {
        return $this->page;
    }

    public function getRequest(): %%namespace%%%%name%%PageRequest
    {
        return $this->request;
    }
}

EOD;

                break;
            case 'RequestEvent':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Storefront\Pagelet\%%namespace%%%%name%%\%%namespace%%%%name%%PageletRequestEvent;
use Shopware\Storefront\Pagelet\ContentHeader\HeaderPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;

class %%namespace%%%%name%%PageRequestEvent extends NestedEvent
{
    public const NAME = '%%namespaceLower%%-%%nameLower%%.page.request';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $httpRequest;

    /**
     * @var %%namespace%%%%name%%PageRequest
     */
    protected $pageRequest;

    public function __construct(Request $httpRequest, CheckoutContext $context, %%namespace%%%%name%%PageRequest $pageRequest)
    {
        $this->context = $context;
        $this->httpRequest = $httpRequest;
        $this->pageRequest = $pageRequest;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new HeaderPageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->getHeaderRequest()),
            new %%namespace%%%%name%%PageletRequestEvent($this->httpRequest, $this->context, $this->pageRequest->get%%namespace%%%%name%%Request())
        ]);
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

    public function getHttpRequest(): Request
    {
        return $this->httpRequest;
    }

    public function get%%namespace%%%%name%%PageRequest(): %%namespace%%%%name%%PageRequest
    {
        return $this->pageRequest;
    }
}
EOD;

                break;
            case 'Request':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\%%namespace%%%%name%%\%%namespace%%%%name%%PageletRequest;
use Shopware\Storefront\Pagelet\ContentHeader\HeaderPageletRequest;

class %%namespace%%%%name%%PageRequest extends Struct
{
    /**
     * @var %%namespace%%%%name%%PageletRequest
     */
    protected $%%namespaceLower%%%%name%%Request;

    /**
     * @var HeaderPageletRequest
     */
    protected $headerRequest;

    public function __construct()
    {
        $this->%%namespaceLower%%%%name%%Request = new %%namespace%%%%name%%PageletRequest();
        $this->headerRequest = new HeaderPageletRequest();
    }

    /**
     * @return %%namespace%%%%name%%PageletRequest
     */
    public function get%%namespace%%%%name%%Request(): %%namespace%%%%name%%PageletRequest
    {
        return $this->%%namespaceLower%%%%name%%Request;
    }

    public function getHeaderRequest(): HeaderPageletRequest
    {
        return $this->headerRequest;
    }
}
EOD;
                break;
            case 'RequestResolver':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class %%namespace%%%%name%%PageRequestResolver extends PageRequestResolver
{
    public function supports(Request $httpRequest, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === %%namespace%%%%name%%PageRequest::class;
    }

    public function resolve(Request $httpRequest, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $request = new %%namespace%%%%name%%PageRequest();

        $this->eventDispatcher->dispatch(
            %%namespace%%%%name%%PageRequestEvent::NAME,
            new %%namespace%%%%name%%PageRequestEvent($httpRequest, $context, $request)
        );

        yield $request;
    }
}
EOD;
                break;
            case 'Loader':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\%%namespace%%%%name%%\%%namespace%%%%name%%PageletLoader;
use Shopware\Storefront\Pagelet\ContentHeader\HeaderPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class %%namespace%%%%name%%PageLoader
{
    /**
     * @var %%namespace%%%%name%%PageletLoader
     */
    private $%%namespaceLower%%%%name%%PageletLoader;

    /**
     * @var HeaderPageletLoader
     */
    private $headerPageletLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        %%namespace%%%%name%%PageletLoader $%%namespaceLower%%%%name%%PageletLoader,
        HeaderPageletLoader $headerPageletLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->%%namespaceLower%%%%name%%PageletLoader = $%%namespaceLower%%%%name%%PageletLoader;
        $this->headerPageletLoader = $headerPageletLoader;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param %%namespace%%%%name%%PageRequest  $request
     * @param CheckoutContext                   $context
     *
     * @return %%namespace%%%%name%%PageStruct
     */
    public function load(%%namespace%%%%name%%PageRequest $request, CheckoutContext $context): %%namespace%%%%name%%PageStruct
    {
        $page = new %%namespace%%%%name%%PageStruct();
        $page->set%%namespace%%%%name%%(
            $this->%%namespaceLower%%%%name%%PageletLoader->load($request->get%%namespace%%%%name%%Request(), $context)
        );

        $page->setHeader(
            $this->headerPageletLoader->load($request->getHeaderRequest(), $context)
        );

        $this->eventDispatcher->dispatch(
            %%namespace%%%%name%%PageLoadedEvent::NAME,
            new %%namespace%%%%name%%PageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
EOD;
                break;
            case 'Subscriber':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Storefront\Event\%%namespace%%Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class %%namespace%%%%name%%PageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            %%namespace%%Events::%%namespaceUPPER%%%%nameUPPER%%_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(%%namespace%%%%name%%PageRequestEvent $event): void
    {
        //$%%namespaceLower%%%%name%%PageRequest = $event->get%%namespace%%%%name%%PageRequest();
        //$%%namespaceLower%%%%name%%PageRequest->get%%namespace%%%%name%%Request()->setxxx($event->getHttpRequest()->attributes->get('xxx'));
    }
}
EOD;
                break;
            case 'Struct':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\%%namespace%%%%name%%\%%namespace%%%%name%%PageletStruct;
use Shopware\Storefront\Pagelet\ContentHeader\HeaderPageletStruct;

class %%namespace%%%%name%%PageStruct extends Struct
{
    /**
     * @var %%namespace%%%%name%%PageletStruct
     */
    protected $%%namespaceLower%%%%name%%;

    /**
     * @var HeaderPageletStruct
     */
    protected $header;

    /**
     * @return %%namespace%%%%name%%PageletStruct
     */
    public function get%%namespace%%%%name%%(): %%namespace%%%%name%%PageletStruct
    {
        return $this->%%namespaceLower%%%%name%%;
    }

    /**
     * @param %%namespace%%%%name%%PageletStruct $%%namespaceLower%%%%name%%
     */
    public function set%%namespace%%%%name%%(%%namespace%%%%name%%PageletStruct $%%namespaceLower%%%%name%%): void
    {
        $this->%%namespaceLower%%%%name%% = $%%namespaceLower%%%%name%%;
    }

    public function getHeader(): HeaderPageletStruct
    {
        return $this->header;
    }

    public function setHeader(HeaderPageletStruct $header): void
    {
        $this->header = $header;
    }
}
EOD;
        }
    }
}
