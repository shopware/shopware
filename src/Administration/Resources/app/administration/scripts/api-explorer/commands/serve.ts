/**
 * @sw-package framework
 */

import * as path from 'node:path';
import { parseArgs } from 'node:util';
import { createDiff } from '../diff/create-diff';
import { createExplorerServer, type ExplorerServer } from '../server/create-explorer-server';
import { createViteUi } from '../server/vite-ui';
import { createSnapshotService } from '../service/snapshot-service';
import { EXIT_OK, UsageError, type CliCommand } from './types';

const ADMIN_ROOT = path.resolve(__dirname, '..', '..', '..');
const REPO_ROOT = path.resolve(ADMIN_ROOT, '..', '..', '..', '..', '..');
const DEFAULT_PORT = 5180;
const DEFAULT_HOST = '0.0.0.0';

function parsePort(value: string, source: string): number {
    const port = Number(value);

    if (!/^\d+$/.test(value) || port > 65535) {
        throw new UsageError(`${source} must be a port number between 0 and 65535, got "${value}"`);
    }

    return port;
}

function waitForShutdownSignal(): Promise<void> {
    return new Promise((resolve) => {
        process.once('SIGINT', () => resolve());
        process.once('SIGTERM', () => resolve());
    });
}

export const serveCommand: CliCommand = {
    summary: 'Start the explorer web UI with its JSON API.',
    usage: [
        'serve [--port <n>] [--host <h>]',
        '',
        `  --port  Port to listen on (default: API_EXPLORER_PORT or ${DEFAULT_PORT})`,
        `  --host  Interface to bind (default: ${DEFAULT_HOST})`,
    ].join('\n'),

    async run(args, io) {
        const { values, positionals } = parseArgs({
            args,
            options: {
                port: { type: 'string' },
                host: { type: 'string' },
            },
            strict: true,
            allowPositionals: true,
        });

        if (positionals.length > 0) {
            throw new UsageError(`Unexpected argument: ${positionals[0]}`);
        }

        if (values.host === '') {
            throw new UsageError('--host must not be empty');
        }

        const envPort = process.env.API_EXPLORER_PORT;
        let port = DEFAULT_PORT;

        if (values.port !== undefined) {
            port = parsePort(values.port, '--port');
        } else if (envPort !== undefined && envPort !== '') {
            port = parsePort(envPort, 'API_EXPLORER_PORT');
        }

        const server: ExplorerServer = await createExplorerServer({
            service: createSnapshotService({ repoRoot: REPO_ROOT, adminRoot: ADMIN_ROOT }),
            createDiff,
            port,
            host: values.host ?? DEFAULT_HOST,
            ui: createViteUi,
        });

        io.stdout(`Admin API Explorer is running at ${server.url}\nPress Ctrl+C to stop.\n`);
        await waitForShutdownSignal();
        await server.close();

        return EXIT_OK;
    },
};
