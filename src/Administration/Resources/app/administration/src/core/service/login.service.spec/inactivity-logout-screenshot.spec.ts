/**
 * @sw-package framework
 */
import html2canvas from 'html2canvas';
import { flushPromises } from '@vue/test-utils';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

jest.mock('html2canvas', () => ({
    __esModule: true,
    default: jest.fn(() => Promise.resolve({ toDataURL: () => 'data:image/jpeg;base64,' })),
}));

describe('src/core/service/login.service.ts', () => {
    it('leaves iframes out of the inactivity logout screenshot', async () => {
        document.body.innerHTML = '<div id="app"></div>';
        Shopware.Application.view = {
            router: {
                currentRoute: { value: { fullPath: '/sw/dashboard/index', name: 'sw.dashboard.index' } },
                push: jest.fn(),
            },
        } as unknown as typeof Shopware.Application.view;

        createLoginService(createHTTPClient(Shopware.Context.api), Shopware.Context.api).logout(true, true);
        await flushPromises();

        const ignoreElements = jest.mocked(html2canvas).mock.calls[0][1]?.ignoreElements;
        expect(ignoreElements?.(document.createElement('iframe'))).toBe(true);
        expect(ignoreElements?.(document.createElement('div'))).toBe(false);
    });
});
