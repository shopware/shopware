import { notification, ui, context } from '@shopware-ag/meteor-admin-sdk';

// location/index
ui.componentSection.add({
    component: 'card',
    positionId: 'sw-product-properties__before',
    props: {
        title: 'Location tests',
        subtitle: 'Testing if the location methods work correctly',
        locationId: 'location-index',
    },
});

ui.componentSection.add({
    component: 'card',
    positionId: 'sw-product-properties__after',
    props: {
        title: 'Card tabs tests',
        subtitle: 'Testing if the the card tabs work correctly',
        locationId: 'card-tabs',
        tabs: [
            {
                name: 'card-tab-1',
                label: 'Tab 1',
                locationId: 'card-tab-1',
            },
            {
                name: 'card-tab-2',
                label: 'Tab 2',
                locationId: 'card-tab-2',
            },
        ],
    },
});

ui.componentSection.add({
    component: 'card',
    positionId: 'ui-tabs-product-example-tab',
    props: {
        title: 'Hello in the new tab',
        locationId: 'ui-modals',
    },
});

ui.componentSection.add({
    component: 'card',
    positionId: 'sw-sales-channel-detail-base-options-delete__after',
    props: {
        title: 'Dataset testing',
        locationId: 'data-dataset',
    },
});

ui.tabs('sw-product-detail').addTabItem({
    label: 'Example',
    componentSectionId: 'ui-tabs-product-example-tab',
});

ui.tabs('sw-custom-field-set-renderer').addTabItem({
    label: 'Example',
    componentSectionId: 'ui-tabs-product-example-tab',
});

ui.actionButton.add({
    name: 'activateLanguageSubscription',
    entity: 'product',
    view: 'detail',
    label: 'Activate language subscription',
    callback: () => {
        // context / subscribe on language changes
        context.subscribeLanguage(({ languageId, systemLanguageId }) => {
            notification.dispatch({
                title: 'Language changes',
                message: `languageId: ${languageId} <br><br> systemLanguageId: ${systemLanguageId}`,
            });
        });

        notification.dispatch({
            title: 'Subscriber activated',
            message: 'The subscriber for language changes was activated',
            variant: 'info',
        });
    },
});

ui.actionButton.add({
    name: 'activateLocaleSubscription',
    entity: 'product',
    view: 'detail',
    label: 'Activate locale subscription',
    callback: () => {
        // context / subscribe on locale changes
        context.subscribeLocale(({ fallbackLocale, locale }) => {
            notification.dispatch({
                title: 'Locale changes',
                message: `locale: ${locale} <br><br> fallbackLocale: ${fallbackLocale}`,
            });
        });

        notification.dispatch({
            title: 'Subscriber activated',
            message: 'The subscriber for locale changes was activated',
            variant: 'info',
        });
    },
});

ui.settings.addSettingsItem({
    label: 'App Settings',
    locationId: 'ui-menu-item-add-menu-item-with-searchbar',
    icon: 'default-object-books',
    displaySearchBar: true,
    tab: 'plugins',
});

ui.settings.addSettingsItem({
    label: 'Without searchbar',
    locationId: 'ui-menu-item-add-menu-item',
    icon: 'default-action-cloud-upload',
    displaySearchBar: false,
    tab: 'shop',
});

ui.actionButton.add({
    action: 'ui-action-button',
    entity: 'product',
    view: 'detail',
    label: 'Test action',
    callback: () => {
        // nothing
        notification.dispatch({
            title: 'Action button click',
            message: 'The action button in the product detail page was clicked',
        });
    },
});

ui.mainModule.addMainModule({
    heading: 'My App',
    locationId: 'ui-main-module-add-main-module',
    displaySearchBar: false,
});

ui.menu.addMenuItem({
    label: 'Test item',
    locationId: 'ui-main-module-add-main-module',
    displaySearchBar: false,
    parent: 'sw-order',
});
ui.menu.addMenuItem({
    label: 'Test with searchbar',
    locationId: 'ui-menu-item-add-menu-item-with-searchbar',
    displaySearchBar: true,
    parent: 'sw-extension',
});
ui.menu.addMenuItem({
    label: 'First item',
    locationId: 'ui-menu-item-add-menu-item-with-searchbar',
    displaySearchBar: true,
    parent: 'sw-extension',
    position: 10,
});
// For testing the third level menu entry
ui.menu.addMenuItem({
    label: 'Third level',
    locationId: 'ui-main-module-add-main-module',
    displaySearchBar: false,
    parent: 'sw-manufacturer',
});
