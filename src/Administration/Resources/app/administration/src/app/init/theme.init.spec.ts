/**
 * @sw-package framework
 */
import { nextTick } from 'vue';
import initializeTheme from 'src/app/init/theme.init';
import useTheme from 'src/app/composables/use-theme';

describe('src/app/init/theme.init.ts', () => {
    afterEach(async () => {
        useTheme().setTheme('system');
        await nextTick();

        localStorage.removeItem('mt-theme');
    });

    it('applies the persisted theme preference to the document root', async () => {
        localStorage.setItem('mt-theme', 'dark');

        initializeTheme();
        await nextTick();

        expect(useTheme().theme.value).toBe('dark');
        expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
    });
});
