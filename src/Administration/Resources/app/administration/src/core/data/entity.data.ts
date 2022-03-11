import Entity, { assignSetterMethod } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import Vue from 'vue';

assignSetterMethod((draft, property, value) => {
    // @ts-expect-error
    Shopware.Application.view.setReactive(draft as Vue, property, value);
});

export default Entity;
