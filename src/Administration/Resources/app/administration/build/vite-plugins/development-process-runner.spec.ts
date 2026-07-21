import concurrently from 'concurrently';
import { startDevelopmentProcesses } from './development-process-runner';

jest.mock('concurrently', () => ({
    __esModule: true,
    default: jest.fn(() => ({
        commands: [],
        result: Promise.resolve([]),
    })),
}));

describe('development-process-runner', () => {
    it('lets the wrapper manage signals without forwarding stdin', () => {
        startDevelopmentProcesses();

        expect(concurrently).toHaveBeenCalledWith(
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
                killOthers: ['failure'],
            },
        );
    });
});
