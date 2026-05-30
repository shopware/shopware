import Plugin from 'src/plugin-system/plugin.class';
import Storage from 'src/helper/storage/storage.helper';

const CUSTOMER_COMMENT_KEY = 'customerComment';
const TOS_KEY = 'tos';

/**
 * Persists checkout customer-specific form data so drafts do not leak
 * across account switches on the same device.
 *
 * @sw-package checkout
 */
export default class CheckoutCustomerStoragePlugin extends Plugin {
    static options = {
        customerId: null,
        storageKey: 'checkoutCustomerStorage',
    };

    init() {
        if (!this.options.customerId) {
            return;
        }

        this._form = this.el.tagName === 'FORM'
            ? this.el
            : (this.el.form ?? document.getElementById(this.el.getAttribute('form')));

        if (!this._form) {
            return;
        }

        this._fieldDefinitions = this._getFieldDefinitions();
        this._fields = this._resolveFields();
        this._clearCurrentCustomerData = this._clearCurrentCustomerData.bind(this);

        this._restoreCurrentCustomerData();
        this._registerEvents();
    }

    _registerEvents() {
        this._fields.forEach((field) => {
            field.events.forEach((eventName) => {
                field.element.addEventListener(eventName, () => this._updateField(field));
            });
        });

        this._form.addEventListener('submit', this._clearCurrentCustomerData);
        this._form.addEventListener('reset', this._clearCurrentCustomerData);
    }

    _restoreCurrentCustomerData() {
        const customerData = this._getCurrentCustomerData();

        this._fields.forEach((field) => {
            field.writeValue(field.element, customerData[field.key] ?? null);
        });
    }

    _getFieldDefinitions() {
        // Add new persisted checkout fields here by defining how to find, read, write, and normalize them.
        return [
            {
                key: CUSTOMER_COMMENT_KEY,
                resolveElement: () => this._getFormElementByName(CUSTOMER_COMMENT_KEY),
                events: ['input', 'change'],
                normalizeValue: (value) => typeof value === 'string' ? value : null,
                readValue: (element) => element.value === '' ? null : element.value,
                writeValue: (element, value) => {
                    if (typeof value === 'string') {
                        element.value = value;
                    }
                },
            },
            {
                key: TOS_KEY,
                resolveElement: () => this._getFormElementByName(TOS_KEY),
                events: ['change'],
                normalizeValue: (value) => value === true ? true : null,
                readValue: (element) => element.checked ? true : null,
                writeValue: (element, value) => {
                    element.checked = value === true;
                },
            },
        ];
    }

    _resolveFields() {
        return this._fieldDefinitions.reduce((fields, fieldDefinition) => {
            const element = fieldDefinition.resolveElement();

            if (!element) {
                return fields;
            }

            fields.push({
                ...fieldDefinition,
                element,
            });

            return fields;
        }, []);
    }

    _updateField(field) {
        this._updateCurrentCustomerData((customerData) => {
            const value = field.readValue(field.element);

            if (value === null) {
                delete customerData[field.key];

                return;
            }

            customerData[field.key] = value;
        });
    }

    _clearCurrentCustomerData() {
        const storedCustomers = this._getStoredCustomers();

        if (!(this.options.customerId in storedCustomers)) {
            return;
        }

        delete storedCustomers[this.options.customerId];
        this._setStoredCustomers(storedCustomers);
    }

    _updateCurrentCustomerData(updater) {
        const storedCustomers = this._getStoredCustomers();
        const customerData = this._getCurrentCustomerData(storedCustomers);

        updater(customerData);

        if (Object.keys(customerData).length === 0) {
            delete storedCustomers[this.options.customerId];
        } else {
            storedCustomers[this.options.customerId] = customerData;
        }

        this._setStoredCustomers(storedCustomers);
    }

    _getCurrentCustomerData(storedCustomers = this._getStoredCustomers()) {
        return this._normalizeCustomerData(storedCustomers[this.options.customerId]) ?? {};
    }

    _getStoredCustomers() {
        return this._getParsedStorage(this.options.storageKey).customers;
    }

    _getParsedStorage(storageKey) {
        const storedValue = Storage.getItem(storageKey);

        if (typeof storedValue !== 'string' || storedValue === '') {
            return {
                customers: {},
                valid: false,
            };
        }

        try {
            const parsedValue = JSON.parse(storedValue);

            if (parsedValue && typeof parsedValue === 'object' && !Array.isArray(parsedValue)) {
                return {
                    customers: this._normalizeStoredCustomers(parsedValue),
                    valid: true,
                };
            }
        } catch (error) {
            return {
                customers: {},
                valid: false,
            };
        }

        return {
            customers: {},
            valid: false,
        };
    }

    _normalizeStoredCustomers(storedCustomers) {
        return Object.entries(storedCustomers).reduce((normalizedCustomers, [customerId, customerData]) => {
            const normalizedCustomerData = this._normalizeCustomerData(customerData);

            if (normalizedCustomerData && Object.keys(normalizedCustomerData).length > 0) {
                normalizedCustomers[customerId] = normalizedCustomerData;
            }

            return normalizedCustomers;
        }, {});
    }

    _normalizeCustomerData(customerData) {
        if (!customerData || typeof customerData !== 'object' || Array.isArray(customerData)) {
            return null;
        }

        return this._fieldDefinitions.reduce((normalizedCustomerData, fieldDefinition) => {
            const normalizedValue = fieldDefinition.normalizeValue(customerData[fieldDefinition.key]);

            if (normalizedValue !== null) {
                normalizedCustomerData[fieldDefinition.key] = normalizedValue;
            }

            return normalizedCustomerData;
        }, {});
    }

    _getFormElementByName(name) {
        return Array.from(document.getElementsByName(name)).find((element) => element.form === this._form) ?? null;
    }

    _setStoredCustomers(storedCustomers) {
        if (Object.keys(storedCustomers).length === 0) {
            Storage.removeItem(this.options.storageKey);

            return;
        }

        Storage.setItem(this.options.storageKey, JSON.stringify(storedCustomers));
    }
}
