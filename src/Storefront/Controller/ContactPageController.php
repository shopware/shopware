<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\ContactForm\ContactFormService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Contact\ContactPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class ContactPageController extends StorefrontController
{
    /**
     * @var ContactPageLoader
     */
    private $contactPageLoader;

    /**
     * @var ContactFormService
     */
    private $contactFormService;

    public function __construct(ContactPageLoader $contactPageLoader, ContactFormService $contactFormService)
    {
        $this->contactPageLoader = $contactPageLoader;
        $this->contactFormService = $contactFormService;
    }

    /**
     * @Route("/contact", name="frontend.page.contact", methods={"GET"})
     */
    public function showContactForm(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $page = $this->contactPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/contact/index.html.twig', [
            'page' => $page,
            'data' => $data,
        ]);
    }

    /**
     * @Route("/contact", name="frontend.page.contact.send", methods={"POST"})
     */
    public function sendContactForm(RequestDataBag $data, SalesChannelContext $context): Response
    {
        try {
            $this->contactFormService->sendContactForm($data, $context);
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.page.contact', [
                'formViolations' => $formViolations,
                'data' => $data,
            ]);
        }

        $this->addFlash('success', $this->trans('contact.success'));

        return $this->redirectToRoute('frontend.page.contact');
    }
}
