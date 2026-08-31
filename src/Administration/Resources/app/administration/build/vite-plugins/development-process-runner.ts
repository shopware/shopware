/**
 * @sw-package framework
 */

import concurrently from 'concurrently';

/**
 * @private
 */
export function startDevelopmentProcesses() {
    return concurrently(
        [
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
        ],
        {
            // concurrently forwards process signals to the complete child process trees.
            killOthers: ['failure'],
        },
    );
}
