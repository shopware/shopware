/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

const strategies = [
    {
        description: '',
        name: 'move-shop-permanently',
    },
    {
        description: '',
        name: 'reinstall-apps',
    },
    {
        description: '',
        name: 'uninstall-apps',
    },
];

describe('sw-app-app-url-changed-modal', () => {
    let wrapper = null;
    let stubs;

    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-app-app-url-changed-modal', {
                sync: true,
            }),
            {
                props: {
                    urlDiff: {
                        oldUrl: 'https://old-url',
                        newUrl: 'https://new-url',
                    },
                },
                global: {
                    stubs,
                    provide: {
                        appUrlChangeService: {
                            fetchResolverStrategies: () => Promise.resolve(strategies),
                            resolveUrlChange: jest.fn(() => Promise.resolve()),
                        },
                        shortcutService: {
                            startEventListener() {},
                            stopEventListener() {},
                        },
                    },
                },
            },
        );
    }

    beforeAll(async () => {
        stubs = {
            'sw-modal': {
                template: `
                    <div class="sw-modal">
                        <slot name="modal-header">
                            <slot name="modal-title"></slot>
                        </slot>
                        <slot name="modal-body">
                             <slot></slot>
                        </slot>
                        <slot name="modal-footer">
                        </slot>
                    </div>`,
            },
            'sw-button': await wrapTestComponent('sw-button'),
            'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
            'sw-loader': await wrapTestComponent('sw-loader'),
            'sw-icon': true,
            'icons-default-basic-shape-circle-filled': {
                template: '<span class="sw-icon sw-icon--default-basic-shape-circle-filled"></span>',
            },
            'icons-regular-circle': {
                template: '<span class="sw-icon sw-icon--regular-circle"></span>',
            },
            'icons-regular-times-s': {
                template: '<span class="sw-icon sw-icon--regular-times-s"></span>',
            },
            'mt-button': true,
            'router-link': true,
        };
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
        await wrapper.vm.$nextTick();
    });

    it('should select the first strategy by default', async () => {
        expect(wrapper.vm.$data.selectedStrategy.name).toMatch(strategies[0].name);
        expect(wrapper.vm.getActiveStyle(strategies[0])).toEqual({
            'sw-app-app-url-changed-modal__content-migration-strategy--active': true,
        });
    });

    it('emmits modal-close if modal is closed', async () => {
        const modal = wrapper.findComponent(stubs['sw-modal']);

        modal.vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('selects clicked strategy', async () => {
        const strategyButtons = wrapper.findAll('.sw-app-app-url-changed-modal__content-migration-strategy');

        await strategyButtons.at(1).trigger('click');

        expect(wrapper.vm.selectedStrategy).toStrictEqual(strategies[1]);

        await strategyButtons.at(2).trigger('click');

        expect(wrapper.vm.selectedStrategy).toStrictEqual(strategies[2]);

        await strategyButtons.at(0).trigger('click');

        expect(wrapper.vm.selectedStrategy).toStrictEqual(strategies[0]);
    });

    it('should send the selected strategy', async () => {
        const urlResolveMock = wrapper.vm.appUrlChangeService.resolveUrlChange;

        wrapper.vm.selectedStrategy = strategies[1];

        wrapper.vm.confirm();

        await flushPromises();

        expect(urlResolveMock.mock.calls[0][0].name).toMatch(strategies[1].name);
        expect(wrapper.emitted()['modal-close']).toBeTruthy();
    });
});
