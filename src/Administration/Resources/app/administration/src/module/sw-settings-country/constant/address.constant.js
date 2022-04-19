export const ADDRESS_VARIABLES = [
    'company',
    'department',
    'title',
    'firstName',
    'lastName',
    'street',
    'city',
    'country',
    'countryState',
    'salutation',
    'phoneNumber',
    'zipcode',
    'additionalAddressLine1',
    'additionalAddressLine2',
];

export const FORMAT_ADDRESS_TEMPLATE = `{{ company }} - {{ department }}
{{ salutation }} {{ title }}
{{ firstName }} {{ lastName }}
{{ street }}
{{ additionalAddressLine1 }}
{{ additionalAddressLine2 }}
{{ zipcode }} {{ city }}
{{ countryState }}
{{ country }}`;

export default {
    ADDRESS_VARIABLES, FORMAT_ADDRESS_TEMPLATE,
};
