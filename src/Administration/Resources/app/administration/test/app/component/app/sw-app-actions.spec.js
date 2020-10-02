import { createLocalVue, mount } from '@vue/test-utils';
import VueRouter from 'vue-router';
import Vuex from 'vuex';
import flushPromises from 'flush-promises';
import { createRouter, actionButtonData } from './_fixtures/app-action-fixtures';
import 'src/app/component/app/sw-app-actions';
import 'src/app/component/base/sw-icon';
import 'src/app/component/base/sw-button';
import 'src/app/component/app/sw-app-action-button';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu';
import 'src/app/component/context-menu/sw-context-menu-item';
import 'src/app/component/utils/sw-popover';

const stubs = {
    'sw-app-action-button': Shopware.Component.build('sw-app-action-button'),
    'sw-icon': Shopware.Component.build('sw-icon'),
    'sw-context-button': Shopware.Component.build('sw-context-button'),
    'sw-context-menu': Shopware.Component.build('sw-context-menu'),
    'sw-context-menu-item': Shopware.Component.build('sw-context-menu-item'),
    'sw-button': Shopware.Component.build('sw-button'),
    'icons-small-more': {
        template: '<span class="sw-icon sw-icon--default-action-external"></span>'
    },
    'sw-popover': Shopware.Component.build('sw-popover')
};

function createWrapper(router) {
    const localVue = createLocalVue();
    localVue.directive('popover', {});
    localVue.use(VueRouter);
    localVue.use(Vuex);

    return mount(Shopware.Component.build('sw-app-actions'), {
        localVue,
        stubs,

        $store: Shopware.State._store,
        router,
        provide: {
            feature: {
                isActive() { return true; }
            },
            appActionButtonService: {
                runAction: jest.fn(),
                getActionButtonsPerView(entity, view) {
                    if (entity === 'product' && view === 'detail') {
                        return Promise.resolve(actionButtonData);
                    }

                    if (entity === 'product' && view === 'list') {
                        return Promise.resolve([]);
                    }

                    return Promise.reject();
                }
            }
        }
    });
}

describe('sw-app-actions', () => {
    let wrapper = null;

    beforeEach(() => {
        Shopware.State.commit('shopwareApps/setSelectedIds', [Shopware.Utils.createId()]);
    });

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
            wrapper = null;
        }
    });

    it('should be a Vue.js component', async () => {
        const router = createRouter();
        wrapper = createWrapper(router);

        router.push({ name: 'sw.product.detail' });
        await flushPromises();

        expect(wrapper.vm).toBeTruthy();

        expect(wrapper.classes()).toEqual(expect.arrayContaining([
            'sw-app-actions'
        ]));
    });

    it('creates an sw-app-action-button per action', async () => {
        const router = createRouter();
        wrapper = createWrapper(router);

        router.push({ name: 'sw.product.detail' });
        await flushPromises();

        // wait created and open context menu
        const contextButton = wrapper.findComponent(stubs['sw-context-button']);
        await contextButton.trigger('click');

        const actionButtons = wrapper.findAllComponents(stubs['sw-app-action-button']);

        expect(actionButtons.length).toBe(2);
        expect(actionButtons.at(0).props('action')).toEqual(actionButtonData[0]);
        expect(actionButtons.at(1).props('action')).toEqual(actionButtonData[1]);
    });

    it('is not rendered if action buttons is empty', async () => {
        const router = createRouter();
        wrapper = createWrapper(router);

        router.push({ name: 'sw.product.list' });
        await flushPromises();

        expect(wrapper.vm.$el).toBeInstanceOf(Comment);
    });

    it('is not rendered if appActionButtonService.appActionButtonService throws an error', async () => {
        const router = createRouter();
        wrapper = createWrapper(router);

        router.push({ name: 'sw.order.detail' });
        await flushPromises();

        expect(wrapper.vm.$el).toBeInstanceOf(Comment);
    });

    it('it calls appActionButtonService.runAction if triggered by context menu button', async () => {
        const router = createRouter();
        wrapper = createWrapper(router);

        router.push({ name: 'sw.product.detail' });
        await flushPromises();

        const contextButton = wrapper.findComponent(stubs['sw-context-button']);

        await contextButton.trigger('click');
        let swAppActions = wrapper.findAllComponents(stubs['sw-app-action-button']);
        await swAppActions.at(0).trigger('click');

        // expect context menu is closed
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        await contextButton.trigger('click');
        swAppActions = wrapper.findAllComponents(stubs['sw-app-action-button']);
        await swAppActions.at(1).trigger('click');

        // expect context menu is closed
        expect(wrapper.findComponent(stubs['sw-context-menu']).exists()).toBe(false);

        const runActionsMock = wrapper.vm.appActionButtonService.runAction;

        expect(runActionsMock.mock.calls.length).toBe(2);
        expect(runActionsMock.mock.calls[0]).toEqual([
            actionButtonData[0].id,
            { ids: Shopware.State.get('shopwareApps').selectedIds }
        ]);

        expect(runActionsMock.mock.calls[1]).toEqual([
            actionButtonData[1].id,
            { ids: Shopware.State.get('shopwareApps').selectedIds }
        ]);
    });
});
