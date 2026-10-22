/**
 * Fails a PHPUnit job when PHP lacks an extension the suites guard on: the tests that need it would only skip.
 *
 * PHP reports its own extensions, so no `php -m | grep` pipeline is involved. Under `pipefail` that pipeline
 * fails at random when `grep -q` exits before `php` has written everything.
 */

import { execFileSync } from 'node:child_process';

type Core = { setFailed(message: string): void; info(message: string): void };

/** Returns the required extensions that are not loaded, compared case-insensitively like `php -m` prints them. */
export function missingExtensions(loaded: readonly string[], required: readonly string[]): string[] {
    const available = new Set(loaded.map((extension) => extension.toLowerCase()));

    return required.filter((extension) => !available.has(extension.toLowerCase()));
}

export function loadedExtensions(): string[] {
    // startup warnings go to stdout on the CLI and would break the JSON
    const output = execFileSync('php', ['-d', 'display_errors=stderr', '-r', 'echo json_encode(get_loaded_extensions());'], {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });

    return JSON.parse(output) as string[];
}

export function checkPhpExtensions(core: Core, required: readonly string[], loaded: readonly string[] = loadedExtensions()): void {
    const missing = missingExtensions(loaded, required);
    if (missing.length > 0) {
        core.setFailed(`PHP extensions not loaded: ${missing.join(', ')}`);

        return;
    }

    core.info(`PHP extensions loaded: ${required.join(', ')}`);
}
