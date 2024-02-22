import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const OpenSearchResultPage = base.extend<{ OpenSearchResultPage: Task }, FixtureTypes>({
    OpenSearchResultPage: async ({ searchPage }, use)=> {
        const task = (searchTerm: string) => {
            return async function OpenSearchResultPage() {
                const url = `search?search=${searchTerm}`;
                await searchPage.page.goto(url);
            }
        };
        await use(task);
    },
});
