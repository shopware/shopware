/**
 * @sw-package fundamentals@after-sales
 */
import swImportExport from './index';

function createTabs() {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swImportExport.computed.tabs.call({
        $router: {
            push: routerPush,
        },
        $t: (snippet) => snippet,
    });

    return {
        routerPush,
        tabs,
    };
}

describe('module/sw-import-export/page/sw-import-export', () => {
    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs();

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-import-export.page.importTab',
                name: 'sw.import.export.index.import',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-import-export.page.exportTab',
                name: 'sw.import.export.index.export',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-import-export.page.profileTab',
                name: 'sw.import.export.index.profiles',
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();
        tabs[1].onClick();
        tabs[2].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, { name: 'sw.import.export.index.import' });
        expect(routerPush).toHaveBeenNthCalledWith(2, { name: 'sw.import.export.index.export' });
        expect(routerPush).toHaveBeenNthCalledWith(3, { name: 'sw.import.export.index.profiles' });
    });
});
