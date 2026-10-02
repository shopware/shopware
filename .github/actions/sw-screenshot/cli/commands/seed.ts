import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { createSeedContext, type SeedContext } from '../../lib/data-service.ts';

/** The shape a seed script must default-export. */
export type SeedScript = (context: SeedContext) => Promise<void> | void;

/**
 * Run a seed script against the provisioned shop.
 *
 * The script receives a ready `TestDataService`, an authenticated Admin API client, and the live
 * storefront sales channel, so it never deals with tokens, UUIDs or foreign keys. Whatever it
 * creates stays in the database — for a pull request that matters, because the same data has to be
 * there again after the source swap.
 *
 * @example
 * // seed.ts
 * export default async ({ data }) => {
 *     await data.createBasicProduct({ name: 'Repro Product', productNumber: 'REPRO-1' });
 *     await data.clearCaches();
 * };
 */
export async function seed(argv: string[]): Promise<void> {
    const script = argv[0];

    if (!script) {
        throw new Error('usage: shot seed <script.ts>');
    }

    const module = (await import(pathToFileURL(resolve(script)).href)) as { default?: SeedScript };

    if (typeof module.default !== 'function') {
        throw new Error(`${script} must default-export a function taking { data, api, salesChannel }`);
    }

    const context = await createSeedContext();
    await module.default(context);

    process.stdout.write('seed complete\n');
}
