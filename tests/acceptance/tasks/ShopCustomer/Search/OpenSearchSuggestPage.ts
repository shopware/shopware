import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const OpenSearchSuggestPage = base.extend<{ OpenSearchSuggestPage: Task }, FixtureTypes>({
    OpenSearchSuggestPage: async ({ searchSuggestPage }, use)=> {
        const task = (searchTerm: string) => {
            return async function OpenSearchSuggestPage() {
                const url = `suggest?search=${searchTerm}`;
                await searchSuggestPage.page.goto(url);
            }
        };
        await use(task);
    },
});
