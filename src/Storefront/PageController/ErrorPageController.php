<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ErrorPageController extends StorefrontController
{
    /**
     * @var ErrorTemplateResolver
     */
    protected $errorTemplateResolver;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(ErrorTemplateResolver $errorTemplateResolver, FlashBagInterface $flashBag, TranslatorInterface $translator)
    {
        $this->errorTemplateResolver = $errorTemplateResolver;
        $this->flashBag = $flashBag;
        $this->translator = $translator;
    }

    public function error(\Exception $exception, Request $request): Response
    {
        try {
            if (!$this->flashBag->has('danger')) {
                $this->flashBag->add('danger', $this->translator->trans('error.message-default'));
            }

            $errorTemplate = $this->errorTemplateResolver->resolve($exception, $request);

            return $this->renderStorefront($errorTemplate->getTemplateName(), $errorTemplate->getArguments());
        } catch (\Exception $e) { //final Fallback
            return $this->renderStorefront(
                '@Storefront/page/error/index.html.twig',
                ['exception' => $exception, 'followingException' => $e]
            );
        }
    }
}
