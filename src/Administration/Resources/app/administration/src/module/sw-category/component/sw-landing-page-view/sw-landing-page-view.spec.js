import swLandingPageView from './index';

/**
 * @sw-package discovery
 */

function createTabs({ canEditLandingPage = true } = {}) {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swLandingPageView.computed.tabs.call({
        acl: {
            can: (privilege) => {
                if (privilege === 'landing_page.editor') {
                    return canEditLandingPage;
                }

                return true;
            },
        },
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

describe('module/sw-category/component/sw-landing-page-view', () => {
    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs();

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-landing-page.view.general',
                name: 'sw.category.landingPageDetail.base',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-landing-page.view.cms',
                name: 'sw.category.landingPageDetail.cms',
                disabled: false,
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching landing page routes when tabs are clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();
        tabs[1].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, { name: 'sw.category.landingPageDetail.base' });
        expect(routerPush).toHaveBeenNthCalledWith(2, { name: 'sw.category.landingPageDetail.cms' });
    });

    it('disables the CMS tab when landing page editing is not allowed', () => {
        const { routerPush, tabs } = createTabs({ canEditLandingPage: false });

        tabs[0].onClick();
        tabs[1].onClick();

        expect(tabs[1]).toEqual(
            expect.objectContaining({
                disabled: true,
            }),
        );
        expect(routerPush).toHaveBeenCalledTimes(1);
        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.category.landingPageDetail.base' });
    });
});
