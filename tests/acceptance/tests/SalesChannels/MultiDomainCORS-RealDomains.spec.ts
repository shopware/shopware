import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'Storefront loads correctly from a second sales channel domain without CORS errors',
    {
        tag: [
            '@CORS',
            '@Storefront',
            '@MultiDomain',
        ],
    },
    async ({ page, ShopCustomer, TestDataService }) => {
        // CI only exposes a single webserver (APP_URL), so we cannot rely on a second port or
        // hostname being reachable. `nip.io` is a wildcard DNS service that resolves any
        // "<anything>.127.0.0.1.nip.io" hostname to 127.0.0.1, which gives us a second, genuinely
        // cross-origin domain (different host) pointing at the very same webserver without any
        // extra CI infrastructure. Shopware is then told about it as a real sales channel domain,
        // exactly like a merchant configuring an additional domain would.
        const appUrl = new URL(process.env.APP_URL ?? 'http://localhost:8000/');
        const secondDomainUrl = `http://second.127.0.0.1.nip.io:${appUrl.port || '80'}/`;

        await TestDataService.createSalesChannelDomain({ url: secondDomainUrl });
        await TestDataService.clearCaches();

        const consoleMessages: { type: string; text: string }[] = [];
        page.on('console', (msg) => {
            consoleMessages.push({ type: msg.type(), text: msg.text() });
        });

        await test.step('Load storefront from the second sales channel domain', async () => {
            await ShopCustomer.goesTo(secondDomainUrl);
            await expect(page).toHaveURL(new RegExp(`^${secondDomainUrl}`));
        });

        await test.step('No CORS errors are logged', async () => {
            const corsErrors = consoleMessages.filter(
                (msg) => msg.type === 'error' && /cors|cross-origin|access-control-allow-origin/i.test(msg.text),
            );

            expect(corsErrors).toHaveLength(0);
        });

        await test.step('Assets are referenced from the second domain', async () => {
            const shopwareScripts = await page.locator('script[src*="shopware.js"]').all();
            expect(shopwareScripts.length).toBeGreaterThan(0);

            const pageHost = new URL(page.url()).host;

            for (const script of shopwareScripts) {
                await expect(script).toHaveAttribute('src', /.+/);
                const src = await script.getAttribute('src');

                if (src && src.startsWith('http')) {
                    expect(new URL(src).host).toBe(pageHost);
                }
            }
        });

        await test.step('Storefront is functional on the second domain', async () => {
            await expect(page).toHaveTitle(/.+/);
            await expect(page.locator('body')).toBeVisible();
        });

        await test.step('Administration is reachable from the second domain', async () => {
            consoleMessages.length = 0;

            await page.goto(new URL('admin/', secondDomainUrl).toString(), { waitUntil: 'domcontentloaded' });
            await expect(page.locator('.sw-login')).toBeVisible();

            const corsErrors = consoleMessages.filter(
                (msg) => msg.type === 'error' && /cors|cross-origin|access-control-allow-origin/i.test(msg.text),
            );

            expect(corsErrors).toHaveLength(0);
        });
    },
);
