/**
 * @sw-package framework
 */

import { serveCommand } from './serve';
import { UsageError } from './types';

const io = { stdout: () => undefined, stderr: () => undefined };

describe('api-explorer/commands/serve', () => {
    const originalPort = process.env.API_EXPLORER_PORT;

    afterEach(() => {
        if (originalPort === undefined) {
            delete process.env.API_EXPLORER_PORT;
        } else {
            process.env.API_EXPLORER_PORT = originalPort;
        }
    });

    it.each([
        [
            ['--port', 'abc'],
            '--port must be a port number',
        ],
        [
            ['--port', '70000'],
            '--port must be a port number',
        ],
        [
            ['--host', ''],
            '--host must not be empty',
        ],
        [
            ['extra'],
            'Unexpected argument: extra',
        ],
    ])('rejects %j before starting anything', async (args, message) => {
        const run = serveCommand.run(args, io);

        await expect(run).rejects.toBeInstanceOf(UsageError);
        await expect(run).rejects.toThrow(message);
    });

    it('rejects an invalid API_EXPLORER_PORT', async () => {
        process.env.API_EXPLORER_PORT = 'eighty';

        await expect(serveCommand.run([], io)).rejects.toThrow('API_EXPLORER_PORT must be a port number');
    });
});
