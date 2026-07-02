<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Shopware\Core\Framework\Log\Package;

/**
 * The expected verdicts of an app-secret recovery attempt. An attempt that ends without a verdict — the app
 * never answered the confirm, the manifest could not be loaded — is exceptional and throws instead.
 *
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum AppSecretRecoveryResult: string
{
    /**
     * A secret the app still trusts was found; a fresh secret is committed and both sides are in sync.
     */
    case Recovered = 'recovered';

    /**
     * No unconfirmed secret is outstanding; there is nothing to do.
     */
    case NothingToRecover = 'nothing_to_recover';

    /**
     * The app rejected every candidate secret: core holds nothing the app trusts, so the registration appears
     * to be claimed by another party (or the rejections were a transient infrastructure error — the
     * unconfirmed list is kept so the attempt can simply be retried).
     */
    case Claimed = 'claimed';
}
