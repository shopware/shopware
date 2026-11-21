import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @sw-package checkout
 */

const { Criteria } = Shopware.Data;

function createAddress(number, overrides = {}) {
    return {
        id: `${number}`,
        customerId: '123',
        street: `Street ${number}`,
        zipcode: `${10000 + number}`,
        city: `City ${number}`,
        country: {
            translated: {
                name: 'USA',
            },
        },
        countryState: {
            translated: {
                name: 'California',
            },
        },
        ...overrides,
    };
}

const addresses = [
    createAddress(1, {
        city: 'San Francisco',
        zipcode: '10332',
        street: 'Summerfield 27',
    }),
    createAddress(2, {
        city: 'London',
        zipcode: '48624',
        street: 'Ebbinghoff 10',
        country: {
            translated: {
                name: 'United Kingdom',
            },
        },
        countryState: {
            translated: {
                name: 'Nottingham',
            },
        },
    }),
];

const manyAddresses = Array.from({ length: 60 }, (_, index) =>
    createAddress(index + 1, index === 54 ? { city: 'Needle City' } : {}),
);

function addressMatchesSearch(address, searchTerm) {
    // Like the server, which does not search the zipcode
    return [
        address.street,
        address.city,
    ].some((value) => value.toLowerCase().includes(searchTerm.toLowerCase()));
}

function createAddressRepository(availableAddresses) {
    const searchedCriteria = [];

    const search = (criteria) => {
        searchedCriteria.push(criteria);

        let result = availableAddresses;

        if (criteria.ids.length > 0) {
            result = result.filter((address) => criteria.ids.includes(address.id));
        }

        if (criteria.term) {
            result = result.filter((address) => addressMatchesSearch(address, criteria.term));
        }

        const offset = (criteria.page - 1) * criteria.limit;

        return Promise.resolve(
            new EntityCollection(
                '/customer-address',
                'customer_address',
                null,
                criteria,
                result.slice(offset, offset + criteria.limit),
                result.length,
            ),
        );
    };

    return {
        searchedCriteria,
        search,
        // Like the real repository, which searches with the given criteria restricted to the id
        get: (id, context, criteria) => {
            const getCriteria = criteria ?? new Criteria(1, 1);
            getCriteria.setIds([id]);

            return search(getCriteria).then((result) => result.get(id));
        },
    };
}

async function createWrapper({ availableAddresses = addresses, props = {} } = {}) {
    const addressRepository = createAddressRepository(availableAddresses);

    const wrapper = mount(
        await wrapTestComponent('sw-order-customer-address-select', {
            sync: true,
        }),
        {
            props: {
                customer: {
                    id: '123',
                },
                value: '1',
                sameAddressLabel: 'Same address',
                sameAddressValue: '2',
                ...props,
            },
            global: {
                stubs: {
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select', { sync: true }),
                    'sw-select-base': await wrapTestComponent('sw-select-base', { sync: true }),
                    'sw-block-field': await wrapTestComponent('sw-block-field', { sync: true }),
                    'sw-base-field': await wrapTestComponent('sw-base-field', { sync: true }),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list', { sync: true }),
                    'sw-select-result': await wrapTestComponent('sw-select-result', { sync: true }),
                    'sw-highlight-text': await wrapTestComponent('sw-highlight-text', { sync: true }),
                    'sw-popover': {
                        template: '<div class="sw-popover"><slot></slot></div>',
                    },
                    'sw-product-variant-info': true,
                    'sw-field-error': true,
                    'sw-loader': true,
                    'mt-loader': true,
                    'sw-inheritance-switch': true,
                    'sw-ai-copilot-badge': true,
                    'sw-help-text': true,
                },
                provide: {
                    repositoryFactory: {
                        create: () => addressRepository,
                    },
                },
            },
        },
    );

    await flushPromises();

    return { wrapper, addressRepository };
}

async function openResultList(wrapper) {
    await wrapper.find('.sw-select__selection').trigger('click');
    await flushPromises();
}

async function searchFor(wrapper, searchTerm) {
    const input = wrapper.find('.sw-entity-single-select__selection-input');
    await input.setValue(searchTerm);
    await input.trigger('input');

    jest.advanceTimersByTime(400);
    await flushPromises();
}

async function scrollToTheEndOfTheResultList(wrapper) {
    const listContent = wrapper.find('.sw-select-result-list__content');

    Object.defineProperty(listContent.element, 'scrollHeight', { value: 1050, configurable: true });
    Object.defineProperty(listContent.element, 'clientHeight', { value: 250, configurable: true });
    Object.defineProperty(listContent.element, 'scrollTop', { value: 800, configurable: true });

    await listContent.trigger('scroll');
    await flushPromises();
}

function getResultTexts(wrapper) {
    return wrapper.findAll('.sw-select-result').map((result) => result.text());
}

function getSelectionText(wrapper) {
    return wrapper.find('.sw-entity-single-select__selection-text').text();
}

describe('src/module/sw-order/component/sw-order-customer-address-select', () => {
    beforeEach(() => {
        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should show address option correctly', async () => {
        const { wrapper } = await createWrapper();

        expect(getSelectionText(wrapper)).toBe('Summerfield 27, 10332, San Francisco, California, USA');

        await openResultList(wrapper);

        expect(getResultTexts(wrapper)).toEqual([
            'Summerfield 27, 10332, San Francisco, California, USA',
            'Ebbinghoff 10, 48624, London, Nottingham, United Kingdom',
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should only search the addresses of the customer', async () => {
        const { wrapper, addressRepository } = await createWrapper();

        await openResultList(wrapper);

        const criteria = addressRepository.searchedCriteria.at(-1);
        expect(criteria.filters).toEqual([Criteria.equals('customerId', '123')]);
        expect(criteria.limit).toBe(25);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should load the pages in a fixed order that keeps the search ranking first',
        async () => {
            const { wrapper, addressRepository } = await createWrapper();

            await openResultList(wrapper);

            expect(addressRepository.searchedCriteria.at(-1).sortings).toEqual([
                Criteria.sort('_score', 'DESC'),
                Criteria.sort('createdAt', 'ASC'),
                Criteria.sort('id', 'ASC'),
            ]);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should render without a customer', async () => {
        const { wrapper, addressRepository } = await createWrapper({
            props: { customer: null, value: '', sameAddressValue: '' },
        });

        expect(getSelectionText(wrapper)).toBe('');

        await openResultList(wrapper);

        expect(addressRepository.searchedCriteria.at(-1).filters).toEqual([Criteria.equals('customerId', null)]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should able to show same address label', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.setProps({
            sameAddressValue: '1',
            sameAddressLabel: 'Same as billing address',
        });

        expect(getSelectionText(wrapper)).toBe('Same as billing address');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should load the next addresses when scrolling to the end of the result list',
        async () => {
            const { wrapper } = await createWrapper({ availableAddresses: manyAddresses });

            await openResultList(wrapper);
            expect(getResultTexts(wrapper)).toHaveLength(25);

            await scrollToTheEndOfTheResultList(wrapper);
            expect(getResultTexts(wrapper)).toHaveLength(50);
            expect(getResultTexts(wrapper).at(-1)).toBe('Street 50, 10050, City 50, California, USA');

            await scrollToTheEndOfTheResultList(wrapper);
            expect(getResultTexts(wrapper)).toHaveLength(60);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should search all addresses of the customer on the server', async () => {
        const { wrapper } = await createWrapper({ availableAddresses: manyAddresses });

        await openResultList(wrapper);
        await searchFor(wrapper, 'Needle');

        expect(getResultTexts(wrapper)).toEqual(['Street 55, 10055, Needle City, California, USA']);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should show the selected address when it is beyond the first page', async () => {
        const { wrapper } = await createWrapper({
            availableAddresses: manyAddresses,
            props: { value: '55' },
        });

        expect(getSelectionText(wrapper)).toBe('Street 55, 10055, Needle City, California, USA');
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'should show an address selected from outside after a search and scrolling',
        async () => {
            const { wrapper } = await createWrapper({
                availableAddresses: manyAddresses,
                props: { sameAddressValue: '40' },
            });

            await openResultList(wrapper);
            await scrollToTheEndOfTheResultList(wrapper);
            await searchFor(wrapper, 'Needle');
            await wrapper.find('.sw-select__selection').trigger('keydown.esc');
            await wrapper.setProps({ value: '30' });
            await flushPromises();

            expect(getSelectionText(wrapper)).toBe('Street 30, 10030, City 30, California, USA');
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('should pass the selected address to the parent once', async () => {
        const onUpdateValue = jest.fn();
        const { wrapper } = await createWrapper({ props: { 'onUpdate:value': onUpdateValue } });

        await openResultList(wrapper);
        await wrapper.findAll('.sw-select-result').at(1).trigger('click');

        expect(onUpdateValue).toHaveBeenCalledTimes(1);
        expect(onUpdateValue).toHaveBeenCalledWith('2');
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should load the addresses of a new customer', async () => {
        const { wrapper, addressRepository } = await createWrapper();

        await wrapper.setProps({ customer: { id: '456' } });
        await openResultList(wrapper);

        expect(addressRepository.searchedCriteria.at(-1).filters).toEqual([Criteria.equals('customerId', '456')]);
    });
});
