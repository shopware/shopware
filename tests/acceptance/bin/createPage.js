#!/usr/bin/env node

// eslint-disable-next-line @typescript-eslint/no-var-requires,no-undef
const fs = require('node:fs');
// eslint-disable-next-line @typescript-eslint/no-var-requires,no-undef
const path = require('node:path');

// eslint-disable-next-line no-undef
const args = process.argv.slice(2);
const page = args[0];

const pageDir = `./page-objects/${path.dirname(page)}`;
const pageFile = `./page-objects/${page}.ts`;
const pageName = path.basename(page);

const content = `import type { Page, Locator } from '@playwright/test';
import type { PageObject } from '@fixtures/PageObject';

export class ${pageName} implements PageObject {
    public readonly headline: Locator;

    constructor(public readonly page: Page) {
        this.headline = page.getByRole('heading', { name: '' });
    }

    async goTo() {
        await this.page.goto('');
    }
}
`;

if (!fs.existsSync(pageDir)) {
    try {
        fs.mkdirSync(pageDir);
    } catch (err) {
        console.error(err);
    }
}

if (!fs.existsSync(pageFile)) {
    try {
        fs.writeFileSync(pageFile, content, { flag: 'a' });
        // eslint-disable-next-line no-console
        console.log('New page object created', pageFile);
        // eslint-disable-next-line no-console
        console.log('Do not forget to check in the file via Git.')
    } catch (err) {
        console.error(err);
    }
} else {
    console.warn('Page object not created. File already exists.');
}
