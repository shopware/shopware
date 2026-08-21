/**
 * @sw-package after-sales
 */

import { mount } from '@vue/test-utils';

const createWrapper = async ({ routeName = 'sw.mail.template.index.templates', routerPush = jest.fn() } = {}) => {
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
                        name: routeName,
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                    $router: {
                        push: routerPush,
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
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        template: '<div class="mt-tabs"></div>',
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                    },
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
    describe('legacy direct-list layout', () => {
        // @deprecated tag:v6.8.0 - The test will be removed with the direct-list mail-template layout.
        it.deprecated('v6.8.0.0')('should render both lists directly', async () => {
            const wrapper = await createWrapper();

            expect(wrapper.findComponent({ name: 'sw-mail-template-list' }).exists()).toBe(true);
            expect(wrapper.findComponent({ name: 'sw-mail-header-footer-list' }).exists()).toBe(true);
            expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
            expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
        });
    });

    /**
     * @deprecated tag:v6.8.0 - This test will be removed.
     */
    describe('tabbed layout', () => {
        it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs with router-view instead of lists', async () => {
            const wrapper = await createWrapper({
                routeName: 'sw.mail.template.index.header_footer',
            });
            const tabs = wrapper.getComponent({ name: 'mt-tabs' });

            expect(tabs.props('positionIdentifier')).toBe('sw-mail-template-index');
            expect(tabs.props('defaultItem')).toBe('sw.mail.template.index.header_footer');
            expect(tabs.props('items')).toEqual([
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
            expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
            expect(wrapper.findComponent({ name: 'router-view' }).exists()).toBe(true);
            expect(wrapper.findComponent({ name: 'sw-mail-template-list' }).exists()).toBe(false);
            expect(wrapper.findComponent({ name: 'sw-mail-header-footer-list' }).exists()).toBe(false);
        });

        it.activeFeatureFlags(['v6.8.0.0'])('should navigate when a meteor tab item is clicked', async () => {
            const routerPush = jest.fn();
            const wrapper = await createWrapper({ routerPush });
            const tabs = wrapper.getComponent({ name: 'mt-tabs' });
            const headerFooterTab = tabs.props('items').find((item) => {
                return item.name === 'sw.mail.template.index.header_footer';
            });

            headerFooterTab.onClick();

            expect(routerPush).toHaveBeenCalledWith({ name: 'sw.mail.template.index.header_footer' });
        });
    });
});
