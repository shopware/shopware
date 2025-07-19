/**
 * @sw-package framework
 */

import { spawn } from 'child_process';
import { build } from 'vite';
import concurrently from 'concurrently';

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
        // Run both processes concurrently in development mode
        const { result } = concurrently([
            {
                command: 'ts-node -T build/plugins.vite.ts',
                name: 'Extensions',
                prefixColor: 'yellow',
            },
            {
                command: 'vite',
                name: 'Administration',
                prefixColor: 'blue',
            },
        ]);

        result.then(
            () => process.exit(0),
            (error) => {
                console.error('Development process failed:', error);
                process.exit(1);
            },
        );
    } else {
        console.error('Invalid VITE_MODE. Must be either "production" or "development"');
        process.exit(1);
    }
}

void main();
