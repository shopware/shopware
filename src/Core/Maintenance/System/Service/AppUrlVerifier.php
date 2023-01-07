<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package core
 *
 * @internal
 */
class AppUrlVerifier
{
    private Client $guzzle;

    private EntityRepository $appRepository;

    private string $appEnv;

    private bool $appUrlCheckDisabled;

    public function __construct(Client $guzzle, EntityRepository $appRepository, string $appEnv, bool $appUrlCheckDisabled)
    {
        $this->guzzle = $guzzle;
        $this->appRepository = $appRepository;
        $this->appEnv = $appEnv;
        $this->appUrlCheckDisabled = $appUrlCheckDisabled;
    }

    public function isAppUrlReachable(Request $request): bool
    {
        if ($this->appEnv !== 'prod' || $this->appUrlCheckDisabled) {
            // dev and test system are often not reachable and this is totally fine
            // problems occur if a prod system can't be reached
            // the check can be disabled manually e.g. for cloud
            return true;
        }

        /** @var string $appUrl */
        $appUrl = EnvironmentHelper::getVariable('APP_URL');

        if (str_starts_with($request->getUri(), $appUrl)) {
            // if the request was made to the same domain as the APP_URL we know that it can be reached
            return true;
        }

        try {
            $response = $this->guzzle->get(rtrim($appUrl, '/') . '/api/_info/version', [
                'headers' => [
                    'Authorization' => $request->headers->get('Authorization'),
                ],
                RequestOptions::TIMEOUT => 1,
                RequestOptions::CONNECT_TIMEOUT => 1,
            ]);

            if ($response->getStatusCode() === Response::HTTP_OK) {
                return true;
            }
        } catch (GuzzleException $e) {
            return false;
        }

        return false;
    }

    public function hasAppsThatNeedAppUrl(Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('appSecret', null)]));

        return $this->appRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
