/**
 * @sw-package after-sales
 */

import { mount } from '@vue/test-utils';
import swMailTemplateIndex from './index';

function createTabs() {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swMailTemplateIndex.computed.tabs.call({
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

const createWrapper = async () => {
    return mount(
        await wrapTestComponent('sw-mail-template-index', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    searchRankingService: {},
                },
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                    <div class="sw-page">
                        <slot name="smart-bar-actions"></slot>
                        <slot name="content"></slot>
                    </div>`,
                    },
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-context-button': {
                        template: `
                    <div class="sw-context-button">
                        <slot name="button"></slot>
                        <slot></slot>
                     </div>`,
                    },
                    'sw-context-menu-item': true,
                    'sw-search-bar': true,
                    'sw-language-switch': true,
                    'sw-mail-template-list': true,
                    'sw-mail-header-footer-list': true,
                    'mt-tabs': true,
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'router-view': true,
                    'sw-button-group': {
                        template: `
                            <span class="sw-button-group"><slot></slot></span>
                        `,
                    },
                },
            },
        },
    );
};

describe('modules/sw-mail-template/page/sw-mail-template-index', () => {
    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs();

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-mail-template.list.tabMailTemplates',
                name: 'sw.mail.template.index.templates',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-mail-template.list.tabHeaderFooter',
                name: 'sw.mail.template.index.header_footer',
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching mail template route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();
        tabs[1].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, { name: 'sw.mail.template.index.templates' });
        expect(routerPush).toHaveBeenNthCalledWith(2, { name: 'sw.mail.template.index.header_footer' });
    });

    it('should not allow to create', async () => {
        const wrapper = await createWrapper();

        const createButtonGroup = wrapper.find('.sw-button-group');

        expect(createButtonGroup.attributes('tooltip-mock-message')).toBe('sw-privileges.tooltip.warning');
        expect(createButtonGroup.attributes('tooltip-mock-disabled')).toBe('false');
        expect(wrapper.find('.sw-mail-template__button-create').attributes('disabled')).toBeDefined();

        const contextButton = wrapper.find('.sw-context-button');

        expect(contextButton.find('button').attributes('disabled')).toBeDefined();
        expect(contextButton.find('sw-context-menu-item-stub').attributes('disabled')).toBeDefined();
    });

    it('should allow to create', async () => {
        global.activeAclRoles = ['mail_templates.creator'];

        const wrapper = await createWrapper();

        const createButtonGroup = wrapper.find('.sw-button-group');

        expect(createButtonGroup.attributes('tooltip-mock-message')).toBe('sw-privileges.tooltip.warning');
        expect(createButtonGroup.attributes('tooltip-mock-disabled')).toBe('true');
        expect(wrapper.find('.sw-mail-template__button-create').attributes('disabled')).toBeUndefined();

        const contextButton = wrapper.find('.sw-context-button');

        expect(contextButton.find('button').attributes('disabled')).toBeUndefined();
        expect(contextButton.find('sw-context-menu-item-stub').attributes('disabled')).toBe('false');
    });

    /**
     * @deprecated tag:v6.8.0 - This test will be removed.
     */
    describe('without v6.8.0.0 feature flag', () => {
        it('should render both lists directly', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.findComponent({ name: 'sw-mail-template-list' }).exists()).toBe(true);
            expect(wrapper.findComponent({ name: 'sw-mail-header-footer-list' }).exists()).toBe(true);
            expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
        });
    });

    /**
     * @deprecated tag:v6.8.0 - This test will be removed.
     */
    describe('with v6.8.0.0 feature flag', () => {
        beforeEach(() => {
            global.activeFeatureFlags = ['v6.8.0.0'];
        });

        afterEach(() => {
            global.activeFeatureFlags = [];
        });

        it('should render tabs with router-view instead of lists', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(true);
            expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
            expect(wrapper.findComponent({ name: 'router-view' }).exists()).toBe(true);
            expect(wrapper.findComponent({ name: 'sw-mail-template-list' }).exists()).toBe(false);
            expect(wrapper.findComponent({ name: 'sw-mail-header-footer-list' }).exists()).toBe(false);
        });
    });
});
