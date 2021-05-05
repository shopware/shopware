import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-event-action/page/sw-event-action-list';
import 'src/app/component/entity/sw-entity-listing';
import 'src/app/component/data-grid/sw-data-grid';
import 'src/app/mixin/listing.mixin';

function mockEventActionData(criteria) {
    const eventActions = [
        {
            id: '9316222dab684c4795752b31e9e2ed2f',
            eventName: 'state_enter.order.state.in_progress',
            actionName: 'action.mail.send',
            active: true,
            config: {
                mail_template_id: 'mailTemplate1'
            }
        },
        {
            id: '231ad971dc1540188825303249934c66',
            eventName: 'user.recovery.request',
            actionName: 'action.mail.send',
            active: true,
            config: {
                mail_template_id: 'mailTemplate2'
            }
        }
    ];

    eventActions.sortings = [];
    eventActions.total = eventActions.length;
    eventActions.criteria = criteria;
    eventActions.context = Shopware.Context.api;

    return eventActions;
}

function mockMailTemplateData() {
    return [
        {
            id: 'mailTemplate1',
            description: 'Shopware default template',
            subject: 'Your order with {{ salesChannel.name }} is being processed.',
            mailTemplateTypeId: '5',
            mailTemplateType: {
                id: '89',
                name: 'Double opt-in on guest orders',
                translated: {
                    name: 'Double opt-in on guest orders'
                }
            },
            translated: {
                description: 'Shopware default template'
            }
        },
        {
            id: 'mailTemplate2',
            description: 'Registration confirmation',
            subject: 'Your order with {{ salesChannel.name }} is being processed.',
            mailTemplateTypeId: '5',
            mailTemplateType: {
                id: '89',
                name: 'Customer registration',
                translated: {
                    name: 'Customer registration'
                }
            },
            translated: {
                description: 'Registration confirmation'
            }
        }
    ];
}

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-event-action-list'), {
        localVue,
        mocks: {
            $route: {
                query: {
                    limit: '25',
                    naturalSorting: false,
                    page: '1',
                    sortBy: 'eventName',
                    sortDirection: 'ASC'
                }
            }
        },
        stubs: {
            'sw-page': {
                template: '<div class="sw-page">' +
                                '<slot name="smart-bar-actions"></slot>' +
                                '<slot name="content"></slot>' +
                                '<slot></slot>' +
                            '</div>'
            },
            'sw-context-menu-item': true,
            'sw-button': true,
            'router-link': true,
            'sw-context-button': true,
            'sw-icon': true,
            'sw-entity-listing': Shopware.Component.build('sw-entity-listing'),
            'sw-data-grid': Shopware.Component.build('sw-data-grid'),
            'sw-data-grid-settings': true,
            'sw-data-grid-skeleton': true,
            'sw-pagination': true,
            'sw-data-grid-column-boolean': true,
            'sw-event-action-list-expand-labels': true,
            'sw-checkbox-field': true
        },

        provide: {
            acl: {
                can: (identifier) => {
                    if (!identifier) {
                        return true;
                    }

                    return privileges.includes(identifier);
                }
            },
            repositoryFactory: {
                create: (entityName) => ({
                    search: jest.fn((criteria) => {
                        if (entityName === 'event_action') {
                            return Promise.resolve(mockEventActionData(criteria));
                        }
                        // entityName `mail_template`
                        return Promise.resolve(mockMailTemplateData(criteria));
                    })
                })
            }
        }
    });
}

describe('src/module/sw-event-action/page/sw-event-action-list', () => {
    it('should be instantiated', () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render entity listing', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-event-action-list__grid').exists()).toBeTruthy();
    });

    it('should load event action data with correct criteria', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();

        // Expect `event_action` repository calls to be correct
        const expectedCriteria = wrapper.vm.eventActionCriteria;
        expect(wrapper.vm.eventActionRepository.search).toHaveBeenCalledWith(expectedCriteria);
        expect(wrapper.vm.eventActionRepository.search).toHaveBeenCalledTimes(1);
    });

    it('should fetch mail templates after entity listing event', async () => {
        const wrapper = createWrapper();
        const spyFetchMailTemplates = jest.spyOn(wrapper.vm, 'fetchMailTemplates');

        await wrapper.vm.$nextTick();

        // Expect entity listing `update-records` event to be fired once
        expect(wrapper.find('.sw-event-action-list__grid').emitted('update-records').length).toBe(1);

        // Expect method to fetch mail templates to be called once
        expect(spyFetchMailTemplates).toHaveBeenCalledTimes(1);

        // Ensure criteria contains mail template ids from previously loaded event actions
        const expectedCriteria = wrapper.vm.mailTemplateCriteria;
        expect(expectedCriteria.ids).toEqual(['mailTemplate1', 'mailTemplate2']);

        // Expect `mail_template` repository calls to be correct
        expect(wrapper.vm.mailTemplateRepository.search).toHaveBeenCalledWith(expectedCriteria);
        expect(wrapper.vm.mailTemplateRepository.search).toHaveBeenCalledTimes(1);
    });

    it('should use custom column slots to display correct event name', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        const rowItem0 = wrapper.find('.sw-event-action-list__grid .sw-data-grid__row--0');
        const rowItem1 = wrapper.find('.sw-event-action-list__grid .sw-data-grid__row--1');
        const cellEventName0 = rowItem0.find('.sw-data-grid__cell--eventName router-link-stub');
        const cellEventName1 = rowItem1.find('.sw-data-grid__cell--eventName router-link-stub');

        // Ensure that the cells have correct event name without dots
        expect(cellEventName0.text()).toBe('global.businessEvents.state_enter_order_state_in_progress');
        expect(cellEventName1.text()).toBe('global.businessEvents.user_recovery_request');
    });

    it('should disable all edit, create and delete actions with viewer privileges', async () => {
        const wrapper = createWrapper([
            'event_action.viewer'
        ]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        // Expect create button to be disabled
        expect(wrapper.find('.sw-event-action-list__action-create').attributes().disabled).toBeTruthy();

        // Expect entity listing to disallow edit and delete
        expect(wrapper.find('.sw-event-action-list__grid').props().allowEdit).toBeFalsy();
        expect(wrapper.find('.sw-event-action-list__grid').props().allowDelete).toBeFalsy();
    });

    it('should enable edit actions with viewer privileges', async () => {
        const wrapper = createWrapper([
            'event_action.viewer',
            'event_action.editor'
        ]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        // Expect create button to be disabled
        expect(wrapper.find('.sw-event-action-list__action-create').attributes().disabled).toBeTruthy();

        // Expect entity listing to allow edit but disallow delete
        expect(wrapper.find('.sw-event-action-list__grid').props().allowEdit).toBeTruthy();
        expect(wrapper.find('.sw-event-action-list__grid').props().allowDelete).toBeFalsy();
    });

    it('should enable create action with creator privileges', async () => {
        const wrapper = createWrapper([
            'event_action.viewer',
            'event_action.editor',
            'event_action.creator'
        ]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        // Expect create button to be enabled
        expect(wrapper.find('.sw-event-action-list__action-create').attributes().disabled).toBeFalsy();

        // Expect entity listing to allow edit but disallow delete
        expect(wrapper.find('.sw-event-action-list__grid').props().allowEdit).toBeTruthy();
        expect(wrapper.find('.sw-event-action-list__grid').props().allowDelete).toBeFalsy();
    });

    it('should enable delete actions with deleter privileges', async () => {
        const wrapper = createWrapper([
            'event_action.viewer',
            'event_action.editor',
            'event_action.creator',
            'event_action.deleter'
        ]);

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        // Expect create button to be enabled
        expect(wrapper.find('.sw-event-action-list__action-create').attributes().disabled).toBeFalsy();

        // Expect entity listing to allow edit and delete
        expect(wrapper.find('.sw-event-action-list__grid').props().allowEdit).toBeTruthy();
        expect(wrapper.find('.sw-event-action-list__grid').props().allowDelete).toBeTruthy();
    });
});
