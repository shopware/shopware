import { resolveShop } from '../../lib/shop.ts';

/**
 * Print where the shop is and how to log in.
 *
 * Run this first in a session — it is cheaper than rediscovering the URL and the credentials by
 * trial and error.
 */
export function info(): void {
    const shop = resolveShop();

    process.stdout.write(
        [
            `storefront   ${shop.appUrl}`,
            `admin        ${shop.appUrl}admin`,
            `admin login  ${shop.username} / ${shop.password}`,
            '',
        ].join('\n'),
    );
}
