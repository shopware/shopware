import { IdProvider, TestDataService } from '@shopware-ag/acceptance-test-suite';
import { createAdminApi, type AdminApi } from './admin-api.ts';

/** Shopware's built-in id for the Storefront sales channel type (`Defaults::SALES_CHANNEL_TYPE_STOREFRONT`). */
const STOREFRONT_TYPE_ID = '8a243080f92e4c719546314b577cf82b';

/** The fields of a sales channel that `TestDataService` needs in order to attach entities to it. */
interface SalesChannelRow {
    id: string;
    currencyId: string;
    languageId: string;
    countryId: string;
    customerGroupId: string;
    paymentMethodId: string;
    navigationCategoryId: string;
}

/**
 * Everything a seeding script is handed.
 *
 * `data` covers the common entities; `api` is the escape hatch for anything `TestDataService` has no
 * factory for (a raw Admin API call, or a `system_config` write).
 */
export interface SeedContext {
    data: TestDataService;
    api: AdminApi;
    /** The live storefront sales channel every seeded entity is attached to. */
    salesChannel: SalesChannelRow;
}

/**
 * Look up the shop's real storefront sales channel.
 *
 * A screenshot has to show the storefront a human would visit — the one the install set up and
 * pointed a domain at — not the throwaway per-worker channel the acceptance suite creates.
 */
async function findStorefrontSalesChannel(api: AdminApi): Promise<SalesChannelRow> {
    const response = await api.post('search/sales-channel', {
        data: {
            limit: 1,
            filter: [
                { type: 'equals', field: 'active', value: true },
                { type: 'equals', field: 'typeId', value: STOREFRONT_TYPE_ID },
            ],
        },
    });

    if (!response.ok()) {
        throw new Error(`sales-channel lookup failed (HTTP ${response.status()}): ${await response.text()}`);
    }

    const body = (await response.json()) as { data?: SalesChannelRow[] };
    const salesChannel = body.data?.[0];

    if (!salesChannel) {
        throw new Error('no active storefront sales channel found — is the shop installed with --basic-setup?');
    }

    return salesChannel;
}

/** Resolve any tax rate to satisfy the mandatory `taxId` on product factories. */
async function findTaxId(api: AdminApi): Promise<string> {
    const response = await api.post('search/tax', { data: { limit: 1, sort: [{ field: 'position', order: 'ASC' }] } });
    const body = (await response.json()) as { data?: { id: string }[] };
    const tax = body.data?.[0];

    if (!tax) {
        throw new Error('no tax rate found in the shop');
    }

    return tax.id;
}

/**
 * Wire up a {@link TestDataService} against the running shop.
 *
 * Use this instead of constructing `TestDataService` yourself: it resolves the foreign keys the
 * factories require (sales channel, tax, currency, language, country, customer group, navigation
 * category) from the live install, so seeded entities are visible in the storefront rather than
 * orphaned. It also disables the suite's automatic cleanup — this run *wants* its data to outlive
 * the script, and for PRs to survive the source swap.
 *
 * @example
 * const { data } = await createSeedContext();
 * const product = await data.createBasicProduct({ name: 'Repro Product' });
 * await data.clearCaches();
 */
export async function createSeedContext(): Promise<SeedContext> {
    const api = await createAdminApi();
    const salesChannel = await findStorefrontSalesChannel(api);
    const taxId = await findTaxId(api);

    const data = new TestDataService(
        api as never,
        new IdProvider(0, process.env['GITHUB_RUN_ID'] ?? 'sw-screenshot'),
        {
            defaultSalesChannel: salesChannel as never,
            defaultTaxId: taxId,
            defaultCurrencyId: salesChannel.currencyId,
            defaultCategoryId: salesChannel.navigationCategoryId,
            defaultLanguageId: salesChannel.languageId,
            defaultCountryId: salesChannel.countryId,
            defaultCustomerGroupId: salesChannel.customerGroupId,
        },
    );

    data.setCleanUp(false);

    return { data, api, salesChannel };
}
