import concurrently from 'concurrently';
import { startDevelopmentProcesses } from './development-process-runner';
import { getViteCommand, isVite8ToolchainActive } from './vite-toolchain';

jest.mock('concurrently', () => ({
    __esModule: true,
    default: jest.fn(() => ({
        commands: [],
        result: Promise.resolve([]),
    })),
}));

jest.mock('./vite-toolchain', () => ({
    getViteCommand: jest.fn(() => 'vite'),
    isVite8ToolchainActive: jest.fn(() => false),
}));

describe('development-process-runner', () => {
    it('lets the wrapper manage signals without forwarding stdin', () => {
        startDevelopmentProcesses();

        expect(concurrently).toHaveBeenCalledWith(
            [
                {
                    command: 'ts-node -T build/vite-plugins/extension-build.ts',
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
                killOthers: ['failure'],
            },
        );
    });

    it('starts only the Administration process when Vite 8 hosts the extension middleware', () => {
        jest.mocked(isVite8ToolchainActive).mockReturnValueOnce(true);

        startDevelopmentProcesses();

        expect(concurrently).toHaveBeenLastCalledWith([{ command: 'vite', name: 'Administration', prefixColor: 'blue' }], {
            killOthers: ['failure'],
        });
    });

    it('starts the Administration with the Vite command of the active toolchain', () => {
        jest.mocked(getViteCommand).mockReturnValueOnce('node "/vite-8/node_modules/vite/bin/vite.js"');

        startDevelopmentProcesses();

        expect(concurrently).toHaveBeenLastCalledWith(
            expect.arrayContaining([
                {
                    command: 'node "/vite-8/node_modules/vite/bin/vite.js"',
                    name: 'Administration',
                    prefixColor: 'blue',
                },
            ]),
            expect.anything(),
        );
    });
});
