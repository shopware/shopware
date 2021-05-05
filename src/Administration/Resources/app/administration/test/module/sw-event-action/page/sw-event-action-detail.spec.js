import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-event-action/page/sw-event-action-detail';

const mockEmptyEventAction = {
    eventName: '',
    actionName: 'new.event.name',
    newEventAction: {
        active: false
    },
    config: {
        mail_template_type_id: 'b926ca5d4ace4efbae2d8474a04ead20'
    }
};

function mockEventAction(id) {
    return {
        id: id,
        eventName: 'existing.event.name',
        actionName: 'action.mail.send',
        active: false,
        config: {
            mail_template_id: '555',
            mail_template_type_id: 'b926ca5d4ace4efbae2d8474a04ead20',
            recipients: { 'mail1@example.com': 'Mail 1' }
        }
    };
}

const mockBusinessEvents = [
    {
        id: '1',
        name: 'checkout.order.placed',
        mailAware: true
    },
    {
        id: '2',
        name: 'absolutely.not.mail.aware',
        mailAware: false
    },
    {
        id: '3',
        name: 'something.actually.happened',
        mailAware: true
    }
];

function createWrapper(eventActionId = null, privileges = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-event-action-detail'), {
        localVue,
        stubs: {
            'sw-page': {
                template: '<div class="sw-page">' +
                                '<slot name="smart-bar-header"></slot>' +
                                '<slot name="smart-bar-actions"></slot>' +
                                '<slot name="content"></slot>' +
                                '<slot></slot>' +
                            '</div>'
            },
            'sw-button': true,
            'sw-button-process': true,
            'sw-card-view': true,
            'sw-single-select': true,
            'sw-entity-single-select': true,
            'sw-card': true,
            'sw-container': true,
            'sw-entity-multi-select': true,
            'sw-switch-field': true,
            'sw-event-action-detail-recipients': true,
            'router-link': true,
            'sw-icon': true,
            'sw-select-rule-create': true,
            'sw-field': true,
            'sw-custom-field-set-renderer': true
        },
        propsData: {
            eventActionId: eventActionId
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
            businessEventService: {
                getBusinessEvents: jest.fn(() => {
                    return Promise.resolve(mockBusinessEvents);
                })
            },
            repositoryFactory: {
                create: () => ({
                    get: jest.fn(() => {
                        return Promise.resolve(mockEventAction(eventActionId));
                    }),
                    create: jest.fn(() => {
                        return mockEmptyEventAction;
                    }),
                    save: jest.fn(() => {
                        return Promise.resolve();
                    })
                })
            },
            customFieldDataProviderService: {
                getCustomFieldSets: () => Promise.resolve([])
            }
        }
    });
}

describe('src/module/sw-event-action/page/sw-event-action-detail', () => {
    it('should be instantiated', () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render all fields', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$nextTick();
        await wrapper.vm.$forceUpdate();

        expect(wrapper.find('.sw-event-action-detail__business-event-select').exists()).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__active-toggle').exists()).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__mail-template-select').exists()).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__sales-channel-select').exists()).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__rule-select').exists()).toBeTruthy();
        expect(wrapper.find('sw-event-action-detail-recipients-stub').exists()).toBeTruthy();
    });

    it('should load existing event action', async () => {
        const wrapper = createWrapper('12345');

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        // Expect to call `event_action` repository get with id
        const expectedCriteria = wrapper.vm.eventActionCriteria;
        expect(wrapper.vm.eventActionRepository.get)
            .toHaveBeenCalledWith(wrapper.vm.eventActionId, Shopware.Context.api, expectedCriteria);

        // Expect to call businessEventService to load all business events
        expect(wrapper.vm.businessEventService.getBusinessEvents).toHaveBeenCalledTimes(1);

        // Ensure that response was assigned to data
        expect(wrapper.vm.eventAction).toMatchObject(mockEventAction(wrapper.vm.eventActionId));
        expect(wrapper.vm.businessEvents).toBeTruthy();

        // Ensure that the page title has current event action name
        expect(wrapper.find('.sw-event-action-detail h2').text()).toBe('global.businessEvents.existing_event_name');
    });

    it('should create new event action when no id is given', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        // Expect to call `event_action` repository create with shopware context
        expect(wrapper.vm.eventActionRepository.create).toHaveBeenCalledWith();

        // Expect to call businessEventService to load all business events
        expect(wrapper.vm.businessEventService.getBusinessEvents).toHaveBeenCalledTimes(1);

        // Ensure that response was assigned to data
        expect(wrapper.vm.eventAction).toEqual(mockEmptyEventAction);
        expect(wrapper.vm.businessEvents).toBeTruthy();

        // Ensure that the page title has "new event action"
        expect(wrapper.find('.sw-event-action-detail h2').text()).toBe('sw-event-action.detail.titleNewEntity');
    });

    it('should load and filter business events', async () => {
        const wrapper = createWrapper();

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        // Expect to call businessEventService to load all business events
        expect(wrapper.vm.businessEventService.getBusinessEvents).toHaveBeenCalledTimes(1);

        const businessEvents = wrapper.vm.businessEvents;

        // Ensure overall length
        expect(businessEvents.length).toBe(2);

        // Ensure object structure
        expect(businessEvents).toEqual(
            expect.arrayContaining([
                expect.not.objectContaining({
                    // No events which are not mailAware should be present
                    mailAware: false
                }),
                expect.objectContaining({
                    // All items should get a label with snippet id
                    label: expect.stringContaining('global.businessEvents')
                })
            ])
        );
    });

    it('should perform save action', async () => {
        const wrapper = createWrapper('54321');

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        // Change the event name
        wrapper.setData({
            eventAction: {
                eventName: 'changed.event.name'
            }
        });

        // Verify event name is inside data prop
        expect(wrapper.vm.eventAction.eventName).toBe('changed.event.name');

        // Execute safe method
        wrapper.vm.onSave();

        await wrapper.vm.$nextTick();

        // Ensure `event_action` repository save has been called
        expect(wrapper.vm.eventActionRepository.save).toHaveBeenCalledWith(wrapper.vm.eventAction);
    });

    it('should not perform save action when no mail template id is given', async () => {
        const wrapper = createWrapper('54321');

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        wrapper.setData({
            eventAction: {
                config: {
                    mail_template_id: undefined,
                    mail_template_type_id: 'b926ca5d4ace4efbae2d8474a04ead20'
                }
            }
        });

        // Verify mail_template_id is not present
        expect(wrapper.vm.eventAction.config.mail_template_id).toBeUndefined();

        // Execute safe method
        wrapper.vm.onSave();

        await wrapper.vm.$nextTick();

        // Ensure `event_action` repository is not being called
        expect(wrapper.vm.eventActionRepository.save).toHaveBeenCalledTimes(0);
    });

    it('should convert recipients array on save', async () => {
        const wrapper = createWrapper('54321');

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        wrapper.setData({
            recipients: [{
                id: '1',
                email: 'test@example.com',
                name: 'Example'
            }, {
                id: '2',
                email: 'info@domain.tld',
                name: 'Info'
            }]
        });

        // Execute safe method
        wrapper.vm.onSave();

        await wrapper.vm.$nextTick();

        // Verify recipients array gets converted and assigned to recipients key in config
        const expectedRecipients = { 'test@example.com': 'Example', 'info@domain.tld': 'Info' };
        expect(wrapper.vm.eventAction.config.recipients).toEqual(expectedRecipients);
    });

    it('should detect recipients are not be changed', async () => {
        const wrapper = createWrapper('54321');

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.recipients).toEqual([
            {
                email: 'mail1@example.com',
                name: 'Mail 1'
            }
        ]);

        // Execute safe method
        wrapper.vm.onSave();

        await wrapper.vm.$nextTick();
        // Verify recipients array gets converted and assigned to recipients key in config
        expect(wrapper.vm.eventAction.config.recipients).toEqual({
            'mail1@example.com': 'Mail 1'
        });
    });

    it('should update recipients when local variable recipients is changed', async () => {
        const wrapper = createWrapper('54321');

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        wrapper.vm.onUpdateRecipientsList([]);
        // Execute safe method
        wrapper.vm.onSave();

        await wrapper.vm.$nextTick();
        // Verify recipients array gets converted and assigned to recipients key in config
        expect(wrapper.vm.eventAction.config.recipients).toBeUndefined();
    });

    it('should disable all interactive buttons and fields with viewer privileges', async () => {
        const wrapper = createWrapper('54321', [
            'event_action.viewer'
        ]);

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        // Expect save button to be disabled
        expect(wrapper.find('.sw-event-action-detail__save-action').attributes().disabled).toBeTruthy();

        // Expect all fields to be disabled
        expect(wrapper.find('.sw-event-action-detail__business-event-select').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__active-toggle').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__mail-template-select').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__sales-channel-select').attributes().disabled).toBeTruthy();
        expect(wrapper.find('.sw-event-action-detail__rule-select').attributes().disabled).toBeTruthy();
    });

    it('should enable all interactive buttons and fields with editor privileges', async () => {
        const wrapper = createWrapper('54321', [
            'event_action.viewer',
            'event_action.editor'
        ]);

        await wrapper.vm.$forceUpdate();
        await wrapper.vm.$nextTick();

        // Expect save button to be disabled
        expect(wrapper.find('.sw-event-action-detail__save-action').attributes().disabled).toBeFalsy();

        // Expect all fields to be disabled
        expect(wrapper.find('.sw-event-action-detail__business-event-select').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-event-action-detail__active-toggle').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-event-action-detail__mail-template-select').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-event-action-detail__sales-channel-select').attributes().disabled).toBeFalsy();
        expect(wrapper.find('.sw-event-action-detail__rule-select').attributes().disabled).toBeFalsy();
    });
});
