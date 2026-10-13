import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

/** Where the agent and the host worker exchange a swap request. */
const REQUEST_DIR = process.env['SW_SHOT_SWAP_DIR'] || '.sw-screenshot-swap';

/** How long to wait for the host to finish. A swap rebuilds assets and runs migrations. */
const TIMEOUT_MS = 30 * 60 * 1000;

const sleep = (ms: number) => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);

/**
 * Ask the host to repoint the shop at the other ref, and wait for it.
 *
 * The swap itself runs outside this sandbox, because the sandbox's PHP has neither PDO nor iconv and
 * `bin/console` needs both. All that crosses the boundary is the word `head` or `base`; the host
 * worker matches it against a literal allowlist and ignores everything else.
 *
 * @example
 * // shot swap head
 * // swapped to head (a1b2c3d)
 */
export function swap(argv: string[]): void {
    const target = argv[0];

    if (target !== 'head' && target !== 'base') {
        throw new Error('usage: shot swap <head|base>');
    }

    mkdirSync(REQUEST_DIR, { recursive: true });

    const result = join(REQUEST_DIR, 'result');
    const log = join(REQUEST_DIR, 'log');

    rmSync(result, { force: true });
    rmSync(log, { force: true });
    writeFileSync(join(REQUEST_DIR, 'request'), target);

    const deadline = Date.now() + TIMEOUT_MS;
    while (!existsSync(result)) {
        if (Date.now() > deadline) {
            throw new Error(`swap to ${target} timed out after ${TIMEOUT_MS / 60000} minutes`);
        }
        sleep(2000);
    }

    const status = Number(readFileSync(result, 'utf8').trim());
    const output = existsSync(log) ? readFileSync(log, 'utf8') : '';

    process.stdout.write(output);

    if (status !== 0) {
        throw new Error(`swap to ${target} failed with exit code ${status}`);
    }
}
