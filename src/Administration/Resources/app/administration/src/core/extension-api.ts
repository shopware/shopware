/**
 * @module core/extension-api
 */

import { ShopwareMessageTypes } from '@shopware-ag/admin-extension-sdk/es/messages.types';
import { handleFactory, MessageDataType } from '@shopware-ag/admin-extension-sdk/es/channel';

export default {
    get handle(): <MESSAGE_TYPE extends keyof ShopwareMessageTypes>(
        type: MESSAGE_TYPE,
        // eslint-disable-next-line max-len
        method: (data: MessageDataType<MESSAGE_TYPE>, additionalInformation: { _event_: MessageEvent<string>}) => Promise<ShopwareMessageTypes[MESSAGE_TYPE]['responseType']> | ShopwareMessageTypes[MESSAGE_TYPE]['responseType'],
    ) => () => void {
        return handleFactory(Shopware.State.get('extensions'));
    },
};
