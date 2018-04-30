<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Firewall;

use Doctrine\DBAL\Connection;
use Shopware\PlatformRequest;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class ApplicationAuthenticator extends AbstractGuardAuthenticator
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Returns a response that directs the user to authenticate.
     *
     * This is called when an anonymous request accesses a resource that
     * requires authentication. The job of this method is to return some
     * response that "helps" the user start into the authentication process.
     *
     * Examples:
     *  A) For a form login, you might redirect to the login page
     *      return new RedirectResponse('/login');
     *  B) For an API token authentication system, you return a 401 response
     *      return new Response('Auth header required', 401);
     *
     * @param Request                 $request       The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     *
     * @return Response
     */
    public function start(SymfonyRequest $request, AuthenticationException $authException = null)
    {
        if ($request->headers->has(PlatformRequest::HEADER_APPLICATION_TOKEN) === false) {
            throw new UnauthorizedHttpException('header', 'Header "X-SW-Application-Token" is required.');
        }

        throw new UnauthorizedHttpException('header', $authException->getMessageKey());
    }

    /**
     * Does the authenticator support the given Request?
     *
     * If this returns false, the authenticator will be skipped.
     *
     * @param SymfonyRequest $request
     *
     * @return bool
     */
    public function supports(SymfonyRequest $request)
    {
        return $request->headers->has(PlatformRequest::HEADER_APPLICATION_TOKEN);
    }

    /**
     * Get the authentication credentials from the request and return them
     * as any type (e.g. an associate array).
     *
     * Whatever value you return here will be passed to getUser() and checkCredentials()
     *
     * For example, for a form login, you might:
     *
     *      return array(
     *          'username' => $request->request->get('_username'),
     *          'password' => $request->request->get('_password'),
     *      );
     *
     * Or for an API token that's on a header, you might use:
     *
     *      return array('api_key' => $request->headers->get('X-API-TOKEN'));
     *
     * @param Request $request
     *
     * @throws \UnexpectedValueException If null is returned
     *
     * @return mixed Any non-null value
     */
    public function getCredentials(SymfonyRequest $request)
    {
        return [
            'access_key' => $request->headers->get(PlatformRequest::HEADER_APPLICATION_TOKEN),
        ];
    }

    /**
     * Return a UserInterface object based on the credentials.
     *
     * The *credentials* are the return value from getCredentials()
     *
     * You may throw an AuthenticationException if you wish. If you return
     * null, then a UsernameNotFoundException is thrown for you.
     *
     * @param mixed                 $credentials
     * @param UserProviderInterface $userProvider
     *
     * @throws AuthenticationException
     *
     * @return UserInterface|null
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        //todo@dr no tenant id
        $builder = $this->connection->createQueryBuilder();
        $application = $builder->select([
                'application.id',
                'application.language_id',
                'application.currency_id',
                'application.payment_method_id',
                'application.shipping_method_id',
                'application.country_id',
                'application.tax_calculation_type',
                'application.catalog_ids',
                'application.language_ids',
            ])
            ->from('application')
            ->where('access_key = :accessKey')
            ->setParameter('accessKey', $credentials['access_key'])
            ->execute()
            ->fetch();

        if (!$application) {
            throw new ApplicationNotFoundException($credentials['access_key']);
        }

        return Application::createFromDatabase($application);
    }

    /**
     * Returns true if the credentials are valid.
     *
     * If any value other than true is returned, authentication will
     * fail. You may also throw an AuthenticationException if you wish
     * to cause authentication to fail.
     *
     * The *credentials* are the return value from getCredentials()
     *
     * @param mixed         $credentials
     * @param UserInterface $user
     *
     * @throws AuthenticationException
     *
     * @return bool
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return $user instanceof Application;
    }

    /**
     * Called when authentication executed, but failed (e.g. wrong username password).
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the login page or a 403 response.
     *
     * If you return null, the request will continue, but the user will
     * not be authenticated. This is probably not what you want to do.
     *
     * @param SymfonyRequest          $request
     * @param AuthenticationException $exception
     *
     * @return Response|null
     */
    public function onAuthenticationFailure(SymfonyRequest $request, AuthenticationException $exception)
    {
        throw $exception;
    }

    /**
     * Called when authentication executed and was successful!
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the last page they visited.
     *
     * If you return null, the current request will continue, and the user
     * will be authenticated. This makes sense, for example, with an API.
     *
     * @param SymfonyRequest $request
     * @param TokenInterface $token
     * @param string         $providerKey The provider (i.e. firewall) key
     *
     * @return Response|null
     */
    public function onAuthenticationSuccess(SymfonyRequest $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    /**
     * Does this method support remember me cookies?
     *
     * Remember me cookie will be set if *all* of the following are met:
     *  A) This method returns true
     *  B) The remember_me key under your firewall is configured
     *  C) The "remember me" functionality is activated. This is usually
     *      done by having a _remember_me checkbox in your form, but
     *      can be configured by the "always_remember_me" and "remember_me_parameter"
     *      parameters under the "remember_me" firewall key
     *  D) The onAuthenticationSuccess method returns a Response object
     *
     * @return bool
     */
    public function supportsRememberMe()
    {
        return false;
    }
}
