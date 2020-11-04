<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\ContactForm\SalesChannel\AbstractContactFormRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Captcha\Annotation\Captcha;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class FormController extends StorefrontController
{
    public const SUBSCRIBE = 'subscribe';
    public const UNSUBSCRIBE = 'unsubscribe';

    /**
     * @var AbstractContactFormRoute
     */
    private $contactFormRoute;

    /**
     * @var AbstractNewsletterSubscribeRoute
     */
    private $subscribeRoute;

    /**
     * @var AbstractNewsletterUnsubscribeRoute
     */
    private $unsubscribeRoute;

    public function __construct(
        AbstractContactFormRoute $contactFormRoute,
        AbstractNewsletterSubscribeRoute $subscribeRoute,
        AbstractNewsletterUnsubscribeRoute $unsubscribeRoute
    ) {
        $this->contactFormRoute = $contactFormRoute;
        $this->subscribeRoute = $subscribeRoute;
        $this->unsubscribeRoute = $unsubscribeRoute;
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/form/contact", name="frontend.form.contact.send", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     * @Captcha
     */
    public function sendContactForm(RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $response = [];

        try {
            $message = $this->contactFormRoute
                ->load($data->toRequestDataBag(), $context)
                ->getResult()
                ->getIndividualSuccessMessage();

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
     * @Since("6.1.0.0")
     * @Route("/form/newsletter", name="frontend.form.newsletter.register.handle", methods={"POST"}, defaults={"XmlHttpRequest"=true})
     * @Captcha
     */
    public function handleNewsletter(Request $request, RequestDataBag $data, SalesChannelContext $context): JsonResponse
    {
        $subscribe = $data->get('option') === self::SUBSCRIBE;

        if ($subscribe) {
            $response = $this->handleSubscribe($request, $data, $context);
        } else {
            $response = $this->handleUnsubscribe($data, $context);
        }

        return new JsonResponse($response);
    }

    private function handleSubscribe(Request $request, RequestDataBag $data, SalesChannelContext $context): array
    {
        try {
            $data->set('storefrontUrl', $request->attributes->get(RequestTransformer::STOREFRONT_URL));

            $this->subscribeRoute->subscribe($data, $context, false);
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
            $this->unsubscribeRoute->unsubscribe($data, $context);
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
