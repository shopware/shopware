<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Transport;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class SmtpOauthTransportFactoryDecorator implements TransportFactoryInterface
{
    public const OPTION_KEY_USE_OAUTH = 'use_oauth';

    public function __construct(
        private readonly EsmtpTransportFactory $decorated,
        private readonly SmtpOauthAuthenticator $smtpOauthAuthenticator,
    ) {
    }

    public function create(Dsn $dsn): TransportInterface
    {
        $transport = $this->decorated->create($dsn);

        if (!$transport instanceof EsmtpTransport) {
            return $transport;
        }

        if ($dsn->getOption(self::OPTION_KEY_USE_OAUTH, false)) {
            $transport->setAuthenticators([$this->smtpOauthAuthenticator]);
        }

        return $transport;
    }

    public function supports(Dsn $dsn): bool
    {
        return $this->decorated->supports($dsn);
    }
}
