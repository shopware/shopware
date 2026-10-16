import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

// Only for a folder that has no baseline yet: regenerating an existing one would suppress every new violation
const FILE = 'eslint-suppressions.json';

if (existsSync(FILE)) {
    console.error(`${FILE} already exists. Fix the violations instead, or prune fixed ones with \`npm run lint:fix\`.`);
    process.exit(1);
}

const result = spawnSync(
    'npm',
    [
        'run',
        'lint',
        '--',
        '--suppress-all',
    ],
    { stdio: 'inherit' },
);

process.exit(result.status ?? 1);
