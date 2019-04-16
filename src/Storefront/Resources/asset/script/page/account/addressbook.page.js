import DomAccess from 'asset/script/helper/dom-access.helper';
import HttpClient from 'asset/script/service/http-client.service';

const client = new HttpClient(window.accessKey, window.contextToken);

// basic js to create a new address
const ACC_ADDRESS_CREATE_ID = 'createAddressForm';
const createAddressForm = document.getElementById(ACC_ADDRESS_CREATE_ID);

if (createAddressForm){
    createAddressForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        const object = {};
        const formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        const json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, function(response) {
            console.log('created address', response);
        });
    });
}

// basic js to set default billing and shipping address
const ACC_ADDRESS_SET_DEFAULT_SELECTOR = 'form[data-address-set-default=true]';
const setDefaultForms = document.querySelectorAll(ACC_ADDRESS_SET_DEFAULT_SELECTOR);

setDefaultForms.forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        client.patch(requestUrl.toLowerCase(), (response) => {
            console.log('set default address', response);
            location.reload(true);
        });
    });
});

// basic js to delete an address
const ACC_ADDRESS_DELETE_SELECTOR = 'form[data-address-delete=true]';
const deleteForms = document.querySelectorAll(ACC_ADDRESS_DELETE_SELECTOR);

deleteForms.forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        client.delete(requestUrl.toLowerCase(), (response) => {
            console.log('deleted address', response);
            location.reload(true);
        });
    });
});
