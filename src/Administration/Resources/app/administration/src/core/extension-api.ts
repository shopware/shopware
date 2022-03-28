/**
 * @module core/extension-api
 */

import { handle } from '@shopware-ag/admin-extension-sdk/es/channel';
import { publishData, getPublishedDataSets } from './service/extension-api-data.service';

export default {
    handle,
    publishData,
    getPublishedDataSets,
};
