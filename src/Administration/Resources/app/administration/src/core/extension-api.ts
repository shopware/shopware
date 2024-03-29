/**
 * @package admin
 *
 * @module core/extension-api
 */

import { handle } from '@shopware-ag/meteor-admin-sdk/es/channel';
import { publishData, getPublishedDataSets } from './service/extension-api-data.service';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    handle,
    publishData,
    getPublishedDataSets,
};
