<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Content\ContactForm\SalesChannel\AbstractContactFormRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\AbstractNewsletterUnsubscribeRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('content')]
class FormController extends StorefrontController
{
    final public const SUBSCRIBE = 'subscribe';
    final public const UNSUBSCRIBE = 'unsubscribe';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContactFormRoute $contactFormRoute,
        private readonly AbstractNewsletterSubscribeRoute $subscribeRoute,
        private readonly AbstractNewsletterUnsubscribeRoute $unsubscribeRoute
    ) {
    }

    #[Route(path: '/form/contact', name: 'frontend.form.contact.send', defaults: ['XmlHttpRequest' => true, '_captcha' => true], methods: ['POST'])]
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
        } catch (RateLimitExceededException $exception) {
            $response[] = [
                'type' => 'info',
                'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => 'info',
                    'content' => $this->trans('error.rateLimitExceeded', ['%seconds%' => $exception->getWaitTime()]),
                ]),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route(path: '/form/newsletter', name: 'frontend.form.newsletter.register.handle', defaults: ['XmlHttpRequest' => true, '_captcha' => true], methods: ['POST'])]
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

    /**
     * @return array<int, array<string|int, mixed>>
     */
    private function handleSubscribe(Request $request, RequestDataBag $data, SalesChannelContext $context): array
    {
        $response = [];

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
        } catch (\Exception) {
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

    /**
     * @return array<int, array<string|int, mixed>>
     */
    private function handleUnsubscribe(RequestDataBag $data, SalesChannelContext $context): array
    {
        $response = [];

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
        } catch (\Exception) {
            $response = [];
        }

        return $response;
    }
}
