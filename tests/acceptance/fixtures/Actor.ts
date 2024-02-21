import type { Page } from '@playwright/test';
import type { PageObject} from '@fixtures/PageObject';
import { test, expect } from '@fixtures/AcceptanceTest';

export class Actor {
    public page: Page;
    public readonly name: string;

    constructor(name, page) {
        this.name = name;
        this.page = page;
    }

    expects = expect;

    async attemptsTo(task) {
        const stepTitle = `${this.name} attempts to ${this.camelCaseToLowerCase(task.name)}`;
        await test.step(stepTitle, async () => await task(this));
    }

    async goesTo(pageObject: PageObject) {
        const stepTitle = `${this.name} navigates to ${this.camelCaseToLowerCase(pageObject.constructor.name)}`;

        await test.step(stepTitle, async () => {
            await pageObject.goTo();

            await this.page.addStyleTag({
                path: 'resources/customAdmin.css',
            });
        });
    }

    private camelCaseToLowerCase(str) {
        return str.replace(/[A-Z]/g, letter => ` ${letter.toLowerCase()}`);
    }
}
