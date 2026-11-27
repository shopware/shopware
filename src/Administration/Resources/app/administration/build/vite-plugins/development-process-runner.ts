/**
 * @sw-package framework
 */

import concurrently from 'concurrently';
import { getViteCommand, isVite8ToolchainActive } from './vite-toolchain';

/**
 * @private
 */
export function startDevelopmentProcesses() {
    return concurrently(
        [
            // With Vite 8 the dev server of the Administration also serves the extensions
            ...(isVite8ToolchainActive()
                ? []
                : [
                      {
                          command: 'ts-node -T build/plugins.vite.ts',
                          name: 'Extensions',
                          prefixColor: 'yellow',
                      },
                  ]),
            {
                command: getViteCommand(),
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
