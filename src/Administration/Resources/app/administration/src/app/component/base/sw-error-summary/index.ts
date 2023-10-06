/**
 * @package admin
 */

import template from './sw-error-summary.html';
import './sw-error-summary.scss';

const { Component } = Shopware;
const { hasOwnProperty } = Shopware.Utils.object;

type error = {
    _code: string,
    _detail: string,
    selfLink: string,
};

/**
 * @private
 */
Component.register('sw-error-summary', {
    template,

    computed: {
        errors(): { [key: string]: number } {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            const allErrors = (Shopware.State.getters['error/getAllApiErrors']() || []) as Array<unknown>;

            // Helper function to recursively get all error objects
            const extractErrorObjects = (errors: Array<unknown>) => {
                return errors.reduce((acc: Array<unknown>, error: unknown) => {
                    if (error === null || typeof error !== 'object') {
                        return acc;
                    }

                    if (error.hasOwnProperty('selfLink') && error.hasOwnProperty('_code') &&
                        error.hasOwnProperty('_detail')) {
                        acc.push(error);

                        return acc;
                    }

                    acc.push(...extractErrorObjects(Object.values(error)));

                    return acc;
                }, []);
            };

            // Retrieve all error objects and remap them to objects just containing a message
            const errorObjects = (extractErrorObjects(allErrors) as Array<error>).map((error): { message: string } => {
                let message = error._detail;

                if (this.$te(`global.error-codes.${error._code}`)) {
                    message = this.$tc(`global.error-codes.${error._code}`);
                }

                return {
                    message,
                };
            });

            // Count the number of errors for each message
            return errorObjects.reduce((acc: { [key: string]: number }, error: { message: string }) => {
                if (!hasOwnProperty(acc, error.message)) {
                    acc[error.message] = 1;
                } else {
                    acc[error.message] += 1;
                }

                return acc;
            }, {});
        },

        errorEntries(): Array<{ message: string, count: number }> {
            return Object.entries(this.errors).map(([message, count]) => ({
                message,
                count,
            }));
        },

        errorCount(): number {
            return Object.values(this.errors).reduce((accumulator, value) => {
                return accumulator + value;
            }, 0);
        },
    },
});
