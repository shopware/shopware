#!/usr/bin/env node

// eslint-disable-next-line @typescript-eslint/no-var-requires,no-undef
const fs = require('node:fs');
// eslint-disable-next-line @typescript-eslint/no-var-requires,no-undef
const path = require('node:path');

// eslint-disable-next-line no-undef
const args = process.argv.slice(2);
const task = args[0];

const taskDir = `./tasks/${path.dirname(task)}`;
const taskFile = `./tasks/${task}.ts`;
const taskName = path.basename(task);

const content = `import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const ${taskName} = base.extend<{ ${taskName}: Task }, FixtureTypes>({
    ${taskName}: async ({ shopCustomer }, use)=> {
        const task = () => {
            return async function ${taskName}() {

                // Add your test content here

            }
        };

        await use(task);
    },
});
`;

if (!fs.existsSync(taskDir)) {
    try {
        fs.mkdirSync(taskDir);
    } catch (err) {
        console.error(err);
    }
}

if (!fs.existsSync(taskFile)) {
    try {
        fs.writeFileSync(taskFile, content, { flag: 'a' });
        // eslint-disable-next-line no-console
        console.log('New task created', taskFile);
        // eslint-disable-next-line no-console
        console.log('Do not forget to check in the file via Git.')
    } catch (err) {
        console.error(err);
    }
} else {
    console.warn('Task not created. File already exists.');
}




