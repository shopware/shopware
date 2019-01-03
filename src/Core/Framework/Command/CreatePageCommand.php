<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Command;

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
            ->addArgument('name', InputArgument::REQUIRED);
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

            return;
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

        $path = rtrim($directory, '/') . '/' . $namespace . $name;
        $files = [
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
            case 'RequestEvent':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\%%namespace%%%%name%%\%%namespace%%%%name%%PageRequest;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

class %%namespace%%%%name%%PageRequestEvent extends Event
{
    public const NAME = '%%namespaceLower%%.%%nameLower%%.page.request.event';

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var %%namespace%%%%name%%PageRequest
     */
    protected $%%namespaceLower%%%%name%%PageRequest;

    public function __construct(Request $request, CheckoutContext $context, %%namespace%%%%name%%PageRequest $%%namespaceLower%%%%name%%PageRequest)
    {
        $this->context = $context;
        $this->request = $request;
        $this->%%namespaceLower%%%%name%%PageRequest = $%%namespaceLower%%%%name%%PageRequest;
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

    public function get%%namespace%%%%name%%PageRequest(): %%namespace%%%%name%%PageRequest
    {
        return $this->%%namespaceLower%%%%name%%PageRequest;
    }
}
EOD;

                break;
            case 'Request':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Storefront\Content\Page\HeaderPageletRequestTrait;

class %%namespace%%%%name%%PageRequest
{
    use HeaderPageletRequestTrait;
}


EOD;
                break;
            case 'RequestResolver':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\PlatformRequest;
/** @todo Do you have corresponding pagelet? uncomment this
use Shopware\Storefront\Page\%%namespace%%%%name%%\%%namespace%%%%name%%PageletRequestEvent;
*/
use Shopware\Storefront\Page\%%namespace%%%%name%%\%%namespace%%%%name%%PageRequestEvent;
use Shopware\Storefront\Checkout\Event\CartInfoPageletRequestEvent;
use Shopware\Storefront\Checkout\Page\CartInfoPageletRequest;
use Shopware\Storefront\Content\Event\CurrencyPageletRequestEvent;
use Shopware\Storefront\Content\Event\LanguagePageletRequestEvent;
use Shopware\Storefront\Content\Event\ShopmenuPageletRequestEvent;
use Shopware\Storefront\Content\Page\CurrencyPageletRequest;
use Shopware\Storefront\Content\Page\LanguagePageletRequest;
use Shopware\Storefront\Content\Page\ShopmenuPageletRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Shopware\Storefront\Listing\Event\NavigationPageletRequestEvent;
use Shopware\Storefront\Listing\Page\NavigationPageletRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class %%namespace%%%%name%%PageRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === %%name%%PageRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $%%namespaceLower%%%%name%%PageRequest = new %%namespace%%%%name%%PageRequest();

        $navigationPageletRequest = new NavigationPageletRequest();
        $event = new NavigationPageletRequestEvent($request, $context, $navigationPageletRequest);
        $this->eventDispatcher->dispatch(NavigationPageletRequestEvent::NAME, $event);
        $%%namespaceLower%%%%name%%PageRequest->setNavigationRequest($navigationPageletRequest);

        $currencyPageletRequest = new CurrencyPageletRequest();
        $event = new CurrencyPageletRequestEvent($request, $context, $currencyPageletRequest);
        $this->eventDispatcher->dispatch(CurrencyPageletRequestEvent::NAME, $event);
        $%%namespaceLower%%%%name%%PageRequest->setCurrencyRequest($currencyPageletRequest);

        $cartInfoPageletRequest = new CartInfoPageletRequest();
        $event = new CartInfoPageletRequestEvent($request, $context, $cartInfoPageletRequest);
        $this->eventDispatcher->dispatch(CartInfoPageletRequestEvent::NAME, $event);
        $%%namespaceLower%%%%name%%PageRequest->setCartInfoRequest($cartInfoPageletRequest);

        $languagePageletRequest = new LanguagePageletRequest();
        $event = new LanguagePageletRequestEvent($request, $context, $languagePageletRequest);
        $this->eventDispatcher->dispatch(LanguagePageletRequestEvent::NAME, $event);
        $%%namespaceLower%%%%name%%PageRequest->setLanguageRequest($languagePageletRequest);

        $shopmenuPageletRequest = new ShopmenuPageletRequest();
        $event = new ShopmenuPageletRequestEvent($request, $context, $shopmenuPageletRequest);
        $this->eventDispatcher->dispatch(ShopmenuPageletRequestEvent::NAME, $event);
        $%%namespaceLower%%%%name%%PageRequest->setShopmenuRequest($shopmenuPageletRequest);

        /** @todo Do you have corresponding pagelet? uncomment this
        $%%namespaceLower%%%%name%%PageletRequest = new %%namespace%%%%name%%PageletRequest();
        $event = new %%namespace%%%%name%%PageletRequestEvent($request, $context, $%%nameLower%%PageletRequest);
        $this->eventDispatcher->dispatch(%%namespace%%%%name%%PageletRequestEvent::NAME, $event);
        $%%namespaceLower%%%%name%%PageRequest->set%%namespace%%%%name%%Request($%%namespaceLower%%%%name%%PageletRequest);
        */
 
        $event = new %%namespace%%%%name%%PageRequestEvent($request, $context, $%%namespaceLower%%%%name%%PageRequest);
        $this->eventDispatcher->dispatch(%%namespace%%%%name%%PageRequestEvent::NAME, $event);

        yield $event->get%%namespace%%%%name%%PageRequest();
    }
}

EOD;
                break;
            case 'Loader':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Page\%%namespace%%%%name%%\%%namespace%%%%name%%PageStruct;
use Shopware\Storefront\Checkout\PageLoader\CartInfoPageletLoader;
use Shopware\Storefront\Content\PageLoader\CurrencyPageletLoader;
use Shopware\Storefront\Content\PageLoader\LanguagePageletLoader;
use Shopware\Storefront\Content\PageLoader\ShopmenuPageletLoader;
use Shopware\Storefront\Framework\Page\PageRequest;

use Shopware\Storefront\Listing\PageLoader\NavigationPageletLoader;

class %%namespace%%%%name%%PageLoader
{
    /**
     * @var %%namespace%%%%name%%PageletLoader
     */
    /** @todo Do you have corresponding pagelet? uncomment this
    private $%%namespaceLower%%%%name%%PageletLoader;
     */

    /**
     * @var NavigationPageletLoader
     */
    private $navigationPageletLoader;

    /**
     * @var CartInfoPageletLoader
     */
    private $cartInfoPageletLoader;

    /**
     * @var ShopmenuPageletLoader
     */
    private $shopmenuPageletLoader;

    /**
     * @var LanguagePageletLoader
     */
    private $languagePageletLoader;

    /**
     * @var CurrencyPageletLoader
     */
    private $currencyPageletLoader;

    /**
     * @param %%namespace%%%%name%%PageRequest     $request
     * @param CheckoutContext $context
     *
     * @return %%namespace%%%%name%%PageStruct
     */
    public function load(%%namespace%%%%name%%PageRequest $request, CheckoutContext $context): %%namespace%%%%name%%PageStruct
    {
        $page = new %%namespace%%%%name%%PageStruct();
/** @todo Do you have corresponding pagelet? uncomment this
        $page->attach(
            $this->%%namespaceLower%%%%name%%PageletLoader->load($request, $context)
        );
*/
        $page = $this->loadFrame($request, $context, $page);

        return $page;
    }

    /**
     * @param PageRequest            $request
     * @param CheckoutContext        $context
     * @param %%namespace%%%%name%%PageStruct $page
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return %%namespace%%%%name%%PageStruct
     */
    private function loadFrame(PageRequest $request, CheckoutContext $context, %%namespace%%%%name%%PageStruct $page): %%namespace%%%%name%%PageStruct
    {
        $page->setNavigation(
            $this->navigationPageletLoader->load($request, $context)
        );

        $page->setCartInfo(
            $this->cartInfoPageletLoader->load($request, $context)
        );

        $page->setShopmenu(
            $this->shopmenuPageletLoader->load($request, $context)
        );

        $page->setLanguage(
            $this->languagePageletLoader->load($request, $context)
        );

        $page->setCurrency(
            $this->currencyPageletLoader->load($request, $context)
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

use Shopware\Storefront\%%namespace%%\Event\%%name%%PageRequestEvent;
use Shopware\Storefront\%%namespace%%\%%namespace%%Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class %%name%%PageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            %%namespace%%Events::%%nameUPPER%%_PAGE_REQUEST => 'transformRequest',
        ];
    }

    public function transformRequest(%%name%%PageRequestEvent $event): void
    {
        $%%nameLower%%PageRequest = $event->get%%name%%PageRequest();
    }
}


EOD;
                break;
            case 'Struct':
                return <<<'EOD'
<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\%%namespace%%%%name%%;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletStructTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;

class %%namespace%%%%name%%PageStruct extends Struct
{
    use HeaderPageletStructTrait;

    /**
     * @var %%name%%PageletStruct
     */
     /** @todo Do you have corresponding pagelet? uncomment this
    protected $%%nameLower%%;
*/
    /**
     * @return %%name%%PageletStruct
     */
     /** @todo Do you have corresponding pagelet? uncomment this
    public function get%%name%%(): CustomerPageletStruct
    {
        return $this->%%nameLower%%;
    }
    */

    /**
     * @param %%name%%PageletStruct $%%nameLower%%
     */
     /** @todo Do you have corresponding pagelet? uncomment this
    public function set%%name%%(%%name%%PageletStruct $%%nameLower%%): void
    {
        $this->%%nameLower%% = $%%nameLower%%;
    }
    */
}

EOD;
        }
    }
}
