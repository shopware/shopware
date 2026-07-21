/**
 * @sw-package fundamentals@framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-settings-currency-detail', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                provide: {
                    repositoryFactory: {
                        create: () => ({
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
                        }),
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
                    'mt-card': {
                        template: `
                        <div class="mt-card">
                            <slot name="toolbar"></slot>
                            <slot></slot>
                        </div>
                    `,
                    },
                    'mt-empty-state': true,
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
                                icon: 'solid-content',
                            },
                        },
                    },
                },
            },
        },
    );
}

describe('module/sw-settings-currency/page/sw-settings-currency-detail', () => {
    it('should not be able to save the currency', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');

        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save the currency', async () => {
        const wrapper = await createWrapper([
            'currencies.editor',
        ]);

        const saveButton = wrapper.find('.sw-settings-currency-detail__save-action');

        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should render country rounding empty state with headline, description, and country icon', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            currency: {
                id: 'currency-id',
                isNew: () => false,
            },
            currencyCountryRoundings: [],
        });

        const emptyState = wrapper.find('mt-empty-state-stub.sw-settings-currency-detail__currency-country-empty-state');

        expect(emptyState.attributes('icon')).toBe('regular-globe');
        expect(emptyState.attributes('headline')).toBe('sw-settings-currency.detail.emptyCountryRoundingsTitle');
        expect(emptyState.attributes('description')).toBe('sw-settings-currency.detail.emptyCountryRoundings');
        expect(emptyState.attributes('role')).toBe('status');
        expect(emptyState.attributes('aria-live')).toBe('polite');
        expect(emptyState.attributes('aria-atomic')).toBe('true');
    });

    it('should render search-specific country rounding empty state copy', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            currency: {
                id: 'currency-id',
                isNew: () => false,
            },
            currencyCountryRoundings: [],
            searchTerm: 'zzz',
        });

        const emptyState = wrapper.find('mt-empty-state-stub.sw-settings-currency-detail__currency-country-empty-state');

        expect(emptyState.attributes('headline')).toBe('sw-settings-currency.detail.emptyCountryRoundingsSearchTitle');
        expect(emptyState.attributes('description')).toBe(
            'sw-settings-currency.detail.emptyCountryRoundingsSearchDescription',
        );
        expect(emptyState.attributes('role')).toBe('status');
        expect(emptyState.attributes('aria-live')).toBe('polite');
        expect(emptyState.attributes('aria-atomic')).toBe('true');
    });
});
