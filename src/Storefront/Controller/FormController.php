<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\ContactForm\ContactFormService;
use Shopware\Core\Content\Newsletter\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Captcha\Annotation\Captcha;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class FormController extends StorefrontController
{
    const SUBSCRIBE = 'subscribe';
    const UNSUBSCRIBE = 'unsubscribe';

    /**
     * @var ContactFormService
     */
    private $contactFormService;

    /**
     * @var NewsletterSubscriptionServiceInterface
     */
    private $newsletterService;

    public function __construct(
        ContactFormService $contactFormService,
        NewsletterSubscriptionServiceInterface $newsletterService
    ) {
        $this->contactFormService = $contactFormService;
        $this->newsletterService = $newsletterService;
    }

    /**
     * @Route("/form/contact", name="frontend.form.contact.send", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     * @Captcha
     */
    public function sendContactForm(RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $response = [];

        try {
            $message = $this->contactFormService->sendContactForm($data, $context);
            if (!$message) {
                $message = $this->trans('contact.success');
            }
            $response[] = [
                'type' => 'success',
                'alert' => $message,
            ];
        } catch (ConstraintViolationException $formViolations) {
            $violations = [];
            foreach ($formViolations->getViolations() as $violation) {
                $violations[] = $violation->getMessage();
            }
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => $violations,
                ]),
            ];
        }

        return new JsonResponse($response);
    }

    /**
     * @Route("/form/newsletter", name="frontend.form.newsletter.register.handle", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     * @Captcha
     */
    public function handleNewsletter(RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $subscribe = $data->get('option') === self::SUBSCRIBE;

        if ($subscribe) {
            $response = $this->handleSubscribe($data, $context);
        } else {
            $response = $this->handleUnsubscribe($data, $context);
        }

        return new JsonResponse($response);
    }

    private function handleSubscribe(RequestDataBag $data, SalesChannelContext $context): array
    {
        try {
            $this->newsletterService->subscribe($data, $context);
            $response[] = [
                'type' => 'success',
                'alert' => $this->trans('newsletter.subscriptionPersistedSuccess'),
            ];
            $response[] = [
                'type' => 'info',
                'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'info',
                    'list' => [$this->trans('newsletter.subscriptionPersistedInfo')],
                ]),
            ];
        } catch (ConstraintViolationException $exception) {
            $errors = [];
            foreach ($exception->getViolations() as $error) {
                $errors[] = $error->getMessage();
            }
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => $errors,
                ]),
            ];
        } catch (\Exception $exception) {
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => [$this->trans('error.message-default')],
                ]),
            ];
        }

        return $response;
    }

    private function handleUnsubscribe(RequestDataBag $data, SalesChannelContext $context): array
    {
        try {
            $this->newsletterService->unsubscribe($data, $context);
            $response[] = [
                'type' => 'success',
                'alert' => $this->trans('newsletter.subscriptionRevokeSuccess'),
            ];
        } catch (ConstraintViolationException $exception) {
            $errors = [];
            foreach ($exception->getViolations() as $error) {
                $errors[] = $error->getMessage();
            }
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => $errors,
                ]),
            ];
        } catch (\Exception $exception) {
            $response = [];
        }

        return $response;
    }
}
