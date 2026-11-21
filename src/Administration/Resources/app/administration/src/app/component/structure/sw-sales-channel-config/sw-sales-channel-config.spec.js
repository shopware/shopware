/**
 * @sw-package discovery
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const salesChannels = Array.from({ length: 30 }, (_, index) => ({
    id: `salesChannel${index + 1}`,
    name: `Sales Channel ${index + 1}`,
    translated: {
        name: `Sales Channel ${index + 1}`,
    },
}));

function createSalesChannelRepository() {
    const searchedCriteria = [];

    const search = (criteria) => {
        searchedCriteria.push(criteria);

        let result = salesChannels;

        if (criteria.ids.length > 0) {
            result = result.filter((salesChannel) => criteria.ids.includes(salesChannel.id));
        }

        if (criteria.term) {
            result = result.filter((salesChannel) => salesChannel.name.includes(criteria.term));
        }

        const offset = (criteria.page - 1) * criteria.limit;

        return Promise.resolve(
            new EntityCollection(
                '/sales-channel',
                'sales_channel',
                null,
                criteria,
                result.slice(offset, offset + criteria.limit),
                result.length,
            ),
        );
    };

    return { searchedCriteria, search };
}

async function createWrapper({ slots = {} } = {}) {
    const salesChannelRepository = createSalesChannelRepository();
    const systemConfigApiService = {
        getValues: jest.fn(() => Promise.resolve({ 'core.example.value': 'value' })),
    };

    const wrapper = mount(await wrapTestComponent('sw-sales-channel-config', { sync: true }), {
        props: {
            domain: 'core.example',
        },
        slots,
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
                    create: () => salesChannelRepository,
                },
                systemConfigApiService,
            },
        },
    });

    await flushPromises();

    return { wrapper, salesChannelRepository, systemConfigApiService };
}

async function openResultList(wrapper) {
    await wrapper.find('.sw-select__selection').trigger('click');
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

describe('src/app/component/structure/sw-sales-channel-config', () => {
    it('should show all sales channels as the default selection', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe(
            'sw-sales-channel-switch.labelDefaultOption',
        );
    });

    it('should load the next sales channels when scrolling to the end of the result list', async () => {
        const { wrapper } = await createWrapper();

        await openResultList(wrapper);
        expect(getResultTexts(wrapper)).toHaveLength(26);
        expect(getResultTexts(wrapper)[0]).toBe('sw-sales-channel-switch.labelDefaultOption');

        await scrollToTheEndOfTheResultList(wrapper);
        expect(getResultTexts(wrapper)).toHaveLength(31);
        expect(getResultTexts(wrapper).at(-1)).toBe('Sales Channel 30');
    });

    it('should load the config of a sales channel beyond the first page', async () => {
        const { wrapper, systemConfigApiService } = await createWrapper();

        await openResultList(wrapper);
        await scrollToTheEndOfTheResultList(wrapper);
        await wrapper
            .findAll('.sw-select-result')
            .find((result) => result.text() === 'Sales Channel 26')
            .trigger('click');
        await flushPromises();

        expect(wrapper.emitted('salesChannelChanged')).toHaveLength(1);
        expect(systemConfigApiService.getValues).toHaveBeenLastCalledWith('core.example', 'salesChannel26');
        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('Sales Channel 26');
    });

    it('should provide the sales channels including the default option to an overridden select', async () => {
        const { wrapper, salesChannelRepository } = await createWrapper({
            slots: {
                select: `<template #select="{ salesChannel }">
                    <ul class="custom-select">
                        <li v-for="item in salesChannel" :key="item.id">{{ item.translated.name }}</li>
                    </ul>
                </template>`,
            },
        });

        expect(salesChannelRepository.searchedCriteria).toHaveLength(1);
        expect(wrapper.findAll('.custom-select li').map((item) => item.text())).toEqual([
            ...salesChannels.slice(0, 25).map((salesChannel) => salesChannel.name),
            'sw-sales-channel-switch.labelDefaultOption',
        ]);
    });
});
