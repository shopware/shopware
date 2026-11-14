/**
 * @sw-package fundamentals@framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(privileges = [], { repository = null, props = {}, router = null } = {}) {
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
                    ...(router ? { $router: router } : {}),
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
        search: jest.fn(() => Promise.resolve([])),
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

        expect(repository.get).toHaveBeenLastCalledWith('firstCurrencyId');
        expect(wrapper.find('input[name="sw-field--currency-name"]').element.value).toBe('Currency firstCurrencyId');

        await wrapper.setProps({ currencyId: 'secondCurrencyId' });
        await flushPromises();

        expect(repository.get).toHaveBeenLastCalledWith('secondCurrencyId');
        expect(wrapper.find('input[name="sw-field--currency-name"]').element.value).toBe('Currency secondCurrencyId');
    });

    it('should drop the country rounding search of the previous currency when the currencyId changes', async () => {
        const repository = createSwitchableCurrencyRepository();
        const wrapper = await createWrapper(['currencies.editor'], {
            repository,
            props: { currencyId: 'firstCurrencyId' },
        });
        await flushPromises();

        wrapper.findComponent('sw-card-filter-stub').vm.$emit('sw-card-filter-term-change', 'old term');
        await flushPromises();

        expect(repository.search).toHaveBeenLastCalledWith(expect.objectContaining({ term: 'old term' }));

        await wrapper.setProps({ currencyId: 'secondCurrencyId' });
        await flushPromises();

        expect(repository.get).toHaveBeenLastCalledWith('secondCurrencyId');
        expect(repository.search).toHaveBeenLastCalledWith(expect.objectContaining({ term: '' }));
    });

    it('should drop the country roundings of the previous currency when creating a new one', async () => {
        const repository = createSwitchableCurrencyRepository();
        const wrapper = await createWrapper(['currencies.editor', 'currencies.creator'], {
            repository,
            props: { currencyId: 'firstCurrencyId' },
        });
        await flushPromises();

        await wrapper.setData({ currencyCountryRoundings: [{ id: 'first-rounding' }] });

        expect(wrapper.find('.sw-settings-currency-detail__currency-country-list').exists()).toBe(true);

        await wrapper.setProps({ currencyId: null });
        await flushPromises();

        expect(wrapper.find('input[name="sw-field--currency-name"]').element.value).toBe('New currency');
        expect(wrapper.find('.sw-settings-currency-detail__currency-country-list').exists()).toBe(false);
        expect(wrapper.find('.sw-settings-currency-detail__currency-country-empty-state').exists()).toBe(true);
    });

    it('should load a newly created currency only once after the redirect to its detail route', async () => {
        const repository = createSwitchableCurrencyRepository();
        const router = { push: jest.fn() };
        const wrapper = await createWrapper(['currencies.creator', 'currencies.editor'], { repository, router });
        await flushPromises();

        await wrapper.find('.sw-settings-currency-detail__save-action').trigger('click');
        await flushPromises();

        expect(repository.save).toHaveBeenCalledTimes(1);
        expect(router.push).toHaveBeenCalledWith({
            name: 'sw.settings.currency.detail',
            params: { id: 'newCurrencyId' },
        });

        await wrapper.setProps({ currencyId: 'newCurrencyId' });
        await flushPromises();

        expect(repository.get).toHaveBeenCalledTimes(1);
        expect(repository.get).toHaveBeenCalledWith('newCurrencyId');
    });
});
