/**
 * @package admin
 *
 * @module core/extension-api
 */

import type { MessageDataType, HandleMethod, BaseMessageOptions } from '@shopware-ag/meteor-admin-sdk/es/channel';
import { handle as sdkHandle } from '@shopware-ag/meteor-admin-sdk/es/channel';
import type { ShopwareMessageTypes } from '@shopware-ag/meteor-admin-sdk/es/message-types';
import MissingPrivilegesError from '@shopware-ag/meteor-admin-sdk/es/_internals/privileges/missing-privileges-error';
import { publishData, getPublishedDataSets } from './service/extension-api-data.service';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function isPromise<T = any>(value: any): value is Promise<T> {
    // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
    return value !== null && typeof value === 'object' && typeof value.then === 'function';
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    // Wrap all handle methods in a function which checks the acl privileges
    handle: <MESSAGE_TYPE extends keyof ShopwareMessageTypes>(
        type: MESSAGE_TYPE,
        method: HandleMethod<MESSAGE_TYPE>,
    ): ReturnType<typeof sdkHandle> => {
        const aclHook = (
            data: MessageDataType<MESSAGE_TYPE> & BaseMessageOptions,
            additionalInformation: { _event_: MessageEvent<string> },
        ): ReturnType<HandleMethod<MESSAGE_TYPE>> => {
            // No privileges to check early return by calling original method
            if (!data.privileges || data.privileges.length === 0) {
                // eslint-disable-next-line @typescript-eslint/no-unsafe-return
                return method(data, additionalInformation);
            }

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return new Promise((resolve, reject) => {
                // We know data.privileges is defined at this point
                const missingPrivileges = data.privileges!.filter((p) => !Shopware.Service('acl').can(p));
                if (missingPrivileges.length > 0) {
                    reject(new MissingPrivilegesError(type, missingPrivileges));
                } else {
                    const result = method(data, additionalInformation);

                    if (isPromise<ShopwareMessageTypes[MESSAGE_TYPE]['responseType']>(result)) {
                        void result.then((rsp) => resolve(rsp)).catch(reject);
                    } else {
                        resolve(result);
                    }
                }
            }) as ReturnType<HandleMethod<MESSAGE_TYPE>>;
        };

        return sdkHandle(type, aclHook);
    },
    publishData,
    getPublishedDataSets,
};
