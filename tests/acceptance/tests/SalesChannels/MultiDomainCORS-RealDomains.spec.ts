import { test } from '@fixtures/AcceptanceTest';

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
    async ({ ShopCustomer, ShopAdmin, TestDataService, InstanceMeta }) => {
        // On PaaS/SaaS, APP_URL is a real remote host, not localhost - the nip.io trick below
        // only works when the shop itself is reachable at 127.0.0.1 (local dev, or CI's single
        // webserver setup), so it fails there with ERR_CONNECTION_REFUSED.
        test.skip(
            InstanceMeta.isPaaS || InstanceMeta.isSaaS,
            'The second-domain nip.io trick only works when the shop is reachable at 127.0.0.1.',
        );

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
        const port = appUrl.port || (appUrl.protocol === 'https:' ? '443' : '80');
        const secondDomainUrl = `${appUrl.protocol}//second-${uniqueId}.127.0.0.1.nip.io:${port}/`;

        await TestDataService.assignSalesChannelCurrency(TestDataService.defaultSalesChannel.id, TestDataService.defaultCurrencyId);
        await TestDataService.createSalesChannelDomain({ url: secondDomainUrl });
        await TestDataService.clearCaches();

        const storefrontConsoleMessages: { type: string; text: string }[] = [];
        ShopCustomer.page.on('console', (msg) => {
            storefrontConsoleMessages.push({ type: msg.type(), text: msg.text() });
        });

        await test.step('Load storefront from the second sales channel domain', async () => {
            await ShopCustomer.goesTo(secondDomainUrl);
            await ShopCustomer.expects(ShopCustomer.page).toHaveURL(new RegExp(`^${secondDomainUrl}`));
        });

        await test.step('No CORS errors are logged', async () => {
            const corsErrors = storefrontConsoleMessages.filter(
                (msg) => msg.type === 'error' && CORS_ERROR_PATTERN.test(msg.text),
            );

            ShopCustomer.expects(corsErrors).toHaveLength(0);
        });

        await test.step('Assets are referenced from the second domain', async () => {
            const shopwareScripts = await ShopCustomer.page.locator('script[src*="shopware.js"]').all();
            ShopCustomer.expects(shopwareScripts.length).toBeGreaterThan(0);

            const pageHost = new URL(ShopCustomer.page.url()).host;

            for (const script of shopwareScripts) {
                await ShopCustomer.expects(script).toHaveAttribute('src', /.+/);
                const src = await script.getAttribute('src');

                if (src && src.startsWith('http')) {
                    ShopCustomer.expects(new URL(src).host).toBe(pageHost);
                }
            }
        });

        await test.step('Storefront is functional on the second domain', async () => {
            await ShopCustomer.expects(ShopCustomer.page).toHaveTitle(/.+/);
            await ShopCustomer.expects(ShopCustomer.page.locator('body')).toBeVisible();
        });

        await test.step('Administration is reachable from the second domain', async () => {
            const adminConsoleMessages: { type: string; text: string }[] = [];
            ShopAdmin.page.on('console', (msg) => {
                adminConsoleMessages.push({ type: msg.type(), text: msg.text() });
            });

            await ShopAdmin.goesTo(new URL('admin/', secondDomainUrl).toString());
            await ShopAdmin.expects(ShopAdmin.page.locator('.sw-login')).toBeVisible();

            const corsErrors = adminConsoleMessages.filter(
                (msg) => msg.type === 'error' && CORS_ERROR_PATTERN.test(msg.text),
            );

            ShopAdmin.expects(corsErrors).toHaveLength(0);
        });
    },
);
