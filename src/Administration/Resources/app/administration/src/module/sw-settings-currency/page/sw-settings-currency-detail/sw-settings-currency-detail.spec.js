/**
 * @sw-package fundamentals@framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], { repository = null, props = {} } = {}) {
    const currencyRepository = {
        create: () => {
            return {
                name: '',
                isoCode: '',
                shortName: '',
                symbol: '',
                factor: 1,
                decimalPrecision: 1,
            };
        },
        save: jest.fn(),
    };

    return mount(
        await wrapTestComponent('sw-settings-currency-detail', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => repository ?? currencyRepository,
                    },
                    acl: {
                        can: (identifier) => {
                            if (!identifier) {
                                return true;
                            }

                            return privileges.includes(identifier);
                        },
                    },
                    customFieldDataProviderService: {
                        getCustomFieldSets: () => Promise.resolve([]),
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                        <div class="sw-page">
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                            <slot></slot>
                        </div>
                    `,
                    },
                    'sw-button-process': true,
                    'sw-language-switch': true,
                    'sw-card-view': true,
                    'sw-container': true,
                    'sw-text-field': true,
                    'sw-language-info': true,
                    'sw-settings-price-rounding': true,
                    'sw-skeleton': true,
                    'sw-card-filter': true,
                    'sw-data-grid-column-boolean': true,
                    'sw-context-menu-item': true,
                    'sw-entity-listing': true,
                    'sw-settings-currency-country-modal': true,
                    'sw-custom-field-set-renderer': true,
                },
                mocks: {
                    $route: {
                        meta: {
                            $module: {
                                icon: 'regular-content',
                            },
                        },
                    },
                },
            },
            props,
        },
    );
}

function createSwitchableCurrencyRepository() {
    return {
        create: () => ({ id: 'newCurrencyId', name: 'New currency', isNew: () => true }),
        save: jest.fn(() => Promise.resolve()),
        get: jest.fn((id) =>
            Promise.resolve({
                id,
                name: `Currency ${id}`,
                countryRoundings: { entity: 'currency_country_rounding', source: `/currency/${id}/country-roundings` },
                isNew: () => false,
            }),
        ),
        search: () => Promise.resolve([]),
    };
}

describe('module/sw-settings-currency/page/sw-settings-currency-detail', () => {
    it('should not be able to save the currency', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the currency', async () => {
        const wrapper = await createWrapper(['currencies.editor']);

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('shows a localized error for a duplicate ISO code', async () => {
        const wrapper = await createWrapper([
            'currencies.editor',
        ]);
        const error = {
            response: {
                data: {
                    errors: [
                        {
                            code: 'SYSTEM__CURRENCY_ISO_CODE_NOT_UNIQUE',
                            meta: {
                                parameters: {
                                    isoCode: 'EUR',
                                },
                            },
                        },
                    ],
                },
            },
        };

        wrapper.vm.currencyRepository.save.mockRejectedValue(error);
        wrapper.vm.createNotificationError = jest.fn();

        await wrapper.vm.onSave();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-currency.detail.notificationIsoCodeAlreadyExists',
        });
    });

    it('should load the new currency when the currencyId changes', async () => {
        const repository = createSwitchableCurrencyRepository();
        const wrapper = await createWrapper(['currencies.editor'], {
            repository,
            props: { currencyId: 'firstCurrencyId' },
        });
        await flushPromises();

        expect(wrapper.vm.currency.id).toBe('firstCurrencyId');

        await wrapper.setProps({ currencyId: 'secondCurrencyId' });
        await flushPromises();

        expect(repository.get).toHaveBeenLastCalledWith('secondCurrencyId');
        expect(wrapper.vm.currency.id).toBe('secondCurrencyId');
    });

    it('should load a newly created currency only once after the redirect to its detail route', async () => {
        const repository = createSwitchableCurrencyRepository();
        const wrapper = await createWrapper(['currencies.creator'], { repository });
        await flushPromises();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            name: 'sw.settings.currency.detail',
            params: { id: 'newCurrencyId' },
        });

        await wrapper.setProps({ currencyId: 'newCurrencyId' });
        await flushPromises();

        expect(repository.get).toHaveBeenCalledTimes(1);
        expect(repository.get).toHaveBeenCalledWith('newCurrencyId');
    });
});
