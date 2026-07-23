/**
 * @sw-package framework
 */

import { spawn } from 'child_process';
import { build } from 'vite';
import { startDevelopmentProcesses } from './build/vite-plugins/development-process-runner';
import { exportViteServerMapping } from './build/vite-plugins/utils';

async function runPluginsBuild(): Promise<void> {
    // Assuming ts-node is installed as a dependency
    return new Promise((resolve, reject) => {
        const process = spawn(
            'ts-node',
            [
                '-T',
                'build/plugins.vite.ts',
            ],
            {
                stdio: 'inherit',
            },
        );

        // When the process closes, then the listeners do not need to be removed anymore
        // eslint-disable-next-line listeners/no-inline-function-event-listener,listeners/no-missing-remove-event-listener
        process.on('close', (code) => {
            if (code === 0) {
                resolve();
            } else {
                reject(new Error(`Plugin build failed with code ${code}`));
            }
        });
    });
}

async function main() {
    const mode = process.env.VITE_MODE;
    const buildOnlyExtensions = process.env.SHOPWARE_ADMIN_BUILD_ONLY_EXTENSIONS === '1';
    await exportViteServerMapping();

    if (mode === 'production') {
        try {
            if (buildOnlyExtensions) {
                // Only run plugins build
                await runPluginsBuild();
            } else {
                // Run Vite build
                await build();
                // Then run plugins build
                await runPluginsBuild();
            }
        } catch (error) {
            console.error('Build failed:', error);
            process.exit(1);
        }
    } else if (mode === 'development') {
        const wasStdinRaw = process.stdin.isTTY && process.stdin.isRaw;
        const restoreTerminal = () => {
            if (process.stdin.isTTY) {
                process.stdin.setRawMode(wasStdinRaw);
            }
        };

        if (process.stdin.isTTY) {
            process.stdin.setRawMode(true);
            // eslint-disable-next-line listeners/no-inline-function-event-listener,listeners/no-missing-remove-event-listener
            process.stdin.on('data', (key: Buffer | string) => {
                if (key.toString().includes('\u0003')) {
                    restoreTerminal();
                    process.kill(process.pid, 'SIGINT');
                }
            });
        }

        // Run both processes concurrently in development mode
        const { result } = startDevelopmentProcesses();

        result.then(
            () => {
                restoreTerminal();
                process.exit(0);
            },
            (error) => {
                restoreTerminal();
                console.error('Development process failed:', error);
                process.exit(1);
            },
        );

        process.once('exit', restoreTerminal);
    } else {
        console.error('Invalid VITE_MODE. Must be either "production" or "development"');
        process.exit(1);
    }
}

void main();
