/**
 * @sw-package framework
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
            'sw-loader': await wrapTestComponent('sw-loader'),
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
        const strategyButtons = wrapper.findAll('.sw-app-app-url-changed-modal__button-strategy');
        expect(strategyButtons).toHaveLength(3);

        expect(strategyButtons[0].classes('sw-app-app-url-changed-modal__button-strategy--active')).toBe(true);
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
        Object.defineProperty(window, 'location', {
            value: { reload: jest.fn() },
        });

        const urlResolveMock = wrapper.vm.appUrlChangeService.resolveUrlChange;

        const strategyButtons = wrapper.findAll('.sw-app-app-url-changed-modal__button-strategy');

        expect(strategyButtons).toHaveLength(3);
        await strategyButtons.at(1).trigger('click');

        await wrapper.get('.mt-button--primary').trigger('click');

        expect(urlResolveMock.mock.calls[0][0].name).toMatch(strategies[1].name);
        expect(window.location.reload).toHaveBeenCalled();
    });
});
