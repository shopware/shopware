<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Fingerprints are put on the shop ID to detect changes in the environment that might suggest a change in the shop ID.
 * They are stored as part of the system configuration and are matched against the runtime stamps any time the shop ID is requested.
 *
 * @see \Shopware\Core\Framework\App\ShopId\ShopIdProvider::getShopId()
 */
#[Package('framework')]
interface Fingerprint
{
    /**
     * A unique identifier for the fingerprint.
     */
    public function getIdentifier(): string;

    /**
     * The score of every mismatching fingerprint is summed up and leads to a suggestion to change the shop ID if it exceeds a certain threshold.
     *
     * A score of 100 indicates a very high certainty that the shop has been permanently moved or cloned to a new environment.
     *
     * @see FingerprintGenerator::STATE_CHANGE_THRESHOLD
     * @see \Shopware\Core\Framework\App\ShopId\FingerprintGenerator::compare()
     */
    public function getScore(): int;

    /**
     * The runtime stamp of the fingerprint, which is used to compare against the stored stamp.
     */
    public function getStamp(): string;
}
