/**
 * @sw-package framework
 */
import fs from 'node:fs';
import path from 'node:path';

const script = fs.readFileSync(
    path.resolve(__dirname, '../../../../../shared/page-loading-screen/page-loading-screen.js'),
    'utf-8',
);

function runPageLoadingScreen(prefersDark: boolean): void {
    Object.defineProperty(window, 'matchMedia', {
        configurable: true,
        writable: true,
        value: jest.fn(() => ({ matches: prefersDark })),
    });

    // The inline script is not a module, so it can only be executed from source.
    // eslint-disable-next-line @typescript-eslint/no-implied-eval, @typescript-eslint/no-unsafe-call
    new Function(script)();
}

describe('shared/page-loading-screen/page-loading-screen.js', () => {
    afterEach(() => {
        localStorage.removeItem('mt-theme');
        document.documentElement.removeAttribute('data-theme');
    });

    it.each([
        { stored: null, prefersDark: true, expected: 'light' },
        { stored: 'system', prefersDark: true, expected: 'dark' },
        { stored: 'dark', prefersDark: false, expected: 'dark' },
        { stored: 'not-a-theme', prefersDark: true, expected: 'light' },
    ])('applies "$expected" for stored "$stored" when prefersDark is $prefersDark', ({ stored, prefersDark, expected }) => {
        if (stored !== null) {
            localStorage.setItem('mt-theme', stored);
        }

        runPageLoadingScreen(prefersDark);

        expect(document.documentElement.getAttribute('data-theme')).toBe(expected);
    });
});
