/**
 * @package admin
 */

import Entity, { assignSetterMethod } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type Vue from 'vue';

assignSetterMethod((draft, property, value) => {
    // @ts-expect-error
    Shopware.Application.view.setReactive(draft as Vue, property, value);
});

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Entity;
