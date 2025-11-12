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

describe('sw-app-shop-id-change-modal', () => {
    let wrapper = null;
    let stubs;

    async function createWrapper() {
        return mount(
            await wrapTestComponent('sw-app-shop-id-change-modal', {
                sync: true,
            }),
            {
                props: {
                    shopIdCheck: {
                        fingerprints: {
                            mismatchingFingerprints: {
                                app_url: {
                                    identifier: 'app_url',
                                    storedStamp: 'old-app-url',
                                    expectedStamp: 'new-app-url',
                                    score: 100,
                                },
                                installation_path: {
                                    identifier: 'installation_path',
                                    storedStamp: 'old-installation-path',
                                    expectedStamp: 'new-installation-path',
                                    score: 100,
                                },
                                sales_channel_domain_urls: {
                                    identifier: 'sales_channel_domain_urls',
                                    storedStamp: 'old-sales-channel-domain-urls',
                                    expectedStamp: 'new-sales-channel-domain-urls',
                                    score: 25,
                                },
                            },
                            matchingFingerprints: [],
                            score: 225,
                            threshold: 75,
                        },
                        apps: [
                            'Test Foo App',
                            'Test Bar App',
                            'Test Baz App',
                        ],
                    },
                },
                global: {
                    stubs,
                    provide: {
                        shopIdChangeService: {
                            getChangeStrategies: () => Promise.resolve(strategies),
                            checkShopId: jest.fn(() => Promise.resolve()),
                            changeShopId: jest.fn(() => Promise.resolve()),
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
            'sw-help-text': true,
        };
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should select the first strategy by default', async () => {
        const strategyButtons = wrapper.findAll('.sw-app-shop-id-change-modal__button-strategy');
        expect(strategyButtons).toHaveLength(3);

        expect(strategyButtons[0].classes('sw-app-shop-id-change-modal__button-strategy--active')).toBe(true);
    });

    it('emits modal-close if modal is closed', async () => {
        const modal = wrapper.findComponent(stubs['sw-modal']);

        modal.vm.$emit('modal-close');

        expect(wrapper.emitted('modal-close')).toBeTruthy();
    });

    it('selects clicked strategy', async () => {
        const strategyButtons = wrapper.findAll('.sw-app-shop-id-change-modal__content-migration-strategy');

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

        const changeShopIdMock = wrapper.vm.shopIdChangeService.changeShopId;

        const strategyButtons = wrapper.findAll('.sw-app-shop-id-change-modal__button-strategy');

        expect(strategyButtons).toHaveLength(3);
        await strategyButtons.at(1).trigger('click');

        await wrapper.get('.mt-button--primary').trigger('click');

        expect(changeShopIdMock.mock.calls[0][0].name).toMatch(strategies[1].name);
        expect(window.location.reload).toHaveBeenCalled();
    });
});
