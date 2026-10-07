/**
 * @sw-package framework
 */
type FixtureShopware = { entryLoaded(marker: string): void };

declare const Shopware: FixtureShopware;

Shopware.entryLoaded('entry code ran');
