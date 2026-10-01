import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { promisify } from 'node:util';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const run = promisify(execFile);
const script = fileURLToPath(new URL('../acceptance-slack-status.bash', import.meta.url));

for (const statusCode of [200, 500]) {
    test(`major ATS reports a failed shard and ${statusCode === 200 ? 'succeeds' : 'fails when Slack rejects it'}`, async (t) => {
        const directory = mkdtempSync(join(tmpdir(), 'ats-slack-test-'));
        t.after(() => rmSync(directory, { recursive: true, force: true }));
        writeFileSync(join(directory, 'gh'), '#!/usr/bin/env node\nconsole.log(process.env.ATS_TEST_JOBS);\n', { mode: 0o755 });

        let payload: { message: string } | undefined;
        const server = createServer((request, response) => {
            let body = '';
            request.on('data', (chunk) => { body += chunk; });
            request.on('end', () => {
                payload = JSON.parse(body);
                response.writeHead(statusCode);
                response.end('test response');
            });
        });
        await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve));
        t.after(() => new Promise<void>((resolve) => server.close(() => resolve())));
        const address = server.address();
        assert.ok(address && typeof address !== 'string');

        const execution = run('bash', [script], {
            env: {
                ...process.env,
                PATH: `${directory}:${process.env.PATH}`,
                REPO: 'shopware/shopware',
                RUN_ID: '123',
                RUN_LABEL: 'Major',
                SLACK_ATS_WORKFLOW_URL: `http://127.0.0.1:${address.port}`,
                ATS_TEST_JOBS: JSON.stringify({ jobs: [
                    { id: 456, name: 'acceptance / acceptance (Platform, v6.8.0.0, 8.2, 1, 3, true)', conclusion: 'failure' },
                    { id: 457, name: 'acceptance / acceptance (Platform, v6.8.0.0, 8.2, 2, 3, true)', conclusion: 'success' },
                    { id: 458, name: 'integration-major / phpunit-major', conclusion: 'failure' },
                ] }),
            },
        });

        if (statusCode === 200) {
            await execution;
        } else {
            await assert.rejects(execution, { code: 22 });
        }
        assert.ok(payload);
        assert.match(payload.message, /^\*Major\*\n/);
        assert.match(payload.message, /❌ .*v6\.8\.0\.0.*https:\/\/github\.com\/shopware\/shopware\/actions\/runs\/123\/job\/456/);
        assert.match(payload.message, /✅ .*v6\.8\.0\.0/);
        assert.doesNotMatch(payload.message, /phpunit-major/);
    });
}
