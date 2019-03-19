import DomAccess from "../../helper/dom-access.helper";
import Client from "../../service/http-client.service";

const client = new Client(window.accessKey, window.contextToken);

// basic js to create a new address
const ACC_ADDRESS_CREATE_ID = 'createAddressForm';
let createAddressForm = document.getElementById(ACC_ADDRESS_CREATE_ID);

if (createAddressForm){
    createAddressForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.srcElement;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        let object = {};
        let formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        let json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, function(response) {
            console.log('created address', response);
        });
    });
}

// basic js to delete an address
const ACC_ADDRESS_DELETE_SELECTOR = 'form[data-address-delete=true]';
let deleteForms = document.querySelectorAll(ACC_ADDRESS_DELETE_SELECTOR);

deleteForms.forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const form = e.srcElement;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        client.delete(requestUrl.toLowerCase(), (response) => {
            console.log('deleted address', response);
            location.reload(true);
        });
    });
});

// basic js to set default billing and shipping address
const ACC_ADDRESS_SET_DEFAULT_SELECTOR = 'form[data-address-set-default=true]';
let setDefaultForms = document.querySelectorAll(ACC_ADDRESS_SET_DEFAULT_SELECTOR);

setDefaultForms.forEach(form => {
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const form = e.srcElement;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        client.patch(requestUrl.toLowerCase(), (response) => {
            console.log('set default address', response);
            location.reload(true);
        });
    });
});
