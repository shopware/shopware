/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import metaInfoPlugin from 'src/app/plugin/meta-info.plugin';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-not-found', { sync: true }), {
        global: {
            plugins: [metaInfoPlugin],
            stubs: {
                'sw-page': {
                    props: ['showSmartBar'],
                    template: '<div class="sw-page" :data-show-smart-bar="showSmartBar"><slot name="content"></slot></div>',
                },
                'sw-not-found-content': true,
            },
        },
    });
}

describe('src/app/component/structure/sw-not-found', () => {
    afterEach(() => {
        document.title = '';
        // @ts-expect-error - the flag is set by the plugin on install
        metaInfoPlugin.pluginInstalled = false;
    });

    it('should render the not found content in a page without smart bar', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.get('.sw-page').attributes('data-show-smart-bar')).toBe('false');
        expect(wrapper.find('sw-not-found-content-stub').exists()).toBe(true);
    });

    it('should set the document title to the not found title', async () => {
        await createWrapper();

        expect(document.title).toBe('sw-not-found.title | global.sw-admin-menu.textShopwareAdmin');
    });
});
