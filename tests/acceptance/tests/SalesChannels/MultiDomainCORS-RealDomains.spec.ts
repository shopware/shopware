import { test, expect } from '@fixtures/AcceptanceTest';

const CORS_ERROR_PATTERN = /cors|cross-origin|access-control-allow-origin/i;

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
        // The hostname must be unique per run: this test is repeated concurrently across
        // workers against the same shop instance (e.g. the "acceptance-tests-changed" CI job),
        // and a fixed hostname would race multiple runs into creating the same sales channel
        // domain URL, tripping its uniqueness constraint.
        const appUrl = new URL(process.env.APP_URL ?? 'http://localhost:8000/');
        const uniqueId = TestDataService.IdProvider.getIdPair().uuid;
        const secondDomainUrl = `http://second-${uniqueId}.127.0.0.1.nip.io:${appUrl.port || '80'}/`;

        await TestDataService.createSalesChannelDomain({ url: secondDomainUrl });
        await TestDataService.clearCaches();

        // `page` in this suite is actually the already-authenticated Administration page
        // (`AdminPage`), not a neutral tab, and `ShopCustomer` drives its own separate
        // storefront page/context - so the storefront checks below use `ShopCustomer.page`,
        // not `page`.
        const storefrontPage = ShopCustomer.page;
        const storefrontConsoleMessages: { type: string; text: string }[] = [];
        storefrontPage.on('console', (msg) => {
            storefrontConsoleMessages.push({ type: msg.type(), text: msg.text() });
        });

        await test.step('Load storefront from the second sales channel domain', async () => {
            await ShopCustomer.goesTo(secondDomainUrl);
            await expect(storefrontPage).toHaveURL(new RegExp(`^${secondDomainUrl}`));
        });

        await test.step('No CORS errors are logged', async () => {
            const corsErrors = storefrontConsoleMessages.filter(
                (msg) => msg.type === 'error' && CORS_ERROR_PATTERN.test(msg.text),
            );

            expect(corsErrors).toHaveLength(0);
        });

        await test.step('Assets are referenced from the second domain', async () => {
            const shopwareScripts = await storefrontPage.locator('script[src*="shopware.js"]').all();
            expect(shopwareScripts.length).toBeGreaterThan(0);

            const pageHost = new URL(storefrontPage.url()).host;

            for (const script of shopwareScripts) {
                await expect(script).toHaveAttribute('src', /.+/);
                const src = await script.getAttribute('src');

                if (src && src.startsWith('http')) {
                    expect(new URL(src).host).toBe(pageHost);
                }
            }
        });

        await test.step('Storefront is functional on the second domain', async () => {
            await expect(storefrontPage).toHaveTitle(/.+/);
            await expect(storefrontPage.locator('body')).toBeVisible();
        });

        await test.step('Administration is reachable from the second domain', async () => {
            const adminConsoleMessages: { type: string; text: string }[] = [];
            page.on('console', (msg) => {
                adminConsoleMessages.push({ type: msg.type(), text: msg.text() });
            });

            await page.goto(new URL('admin/', secondDomainUrl).toString(), { waitUntil: 'domcontentloaded' });
            await expect(page.locator('.sw-login')).toBeVisible();

            const corsErrors = adminConsoleMessages.filter(
                (msg) => msg.type === 'error' && CORS_ERROR_PATTERN.test(msg.text),
            );

            expect(corsErrors).toHaveLength(0);
        });
    },
);
