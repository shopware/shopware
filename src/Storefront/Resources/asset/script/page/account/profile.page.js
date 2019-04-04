import DomAccess from 'asset/script/helper/dom-access.helper';
import HttpClient from 'asset/script/service/http-client.service';

const client = new HttpClient(window.accessKey, window.contextToken);

// basic js to update customer data
const ACC_PROFILE_PERSONAL_ID = 'profilePersonalForm';
const profilePersonalForm = document.getElementById(ACC_PROFILE_PERSONAL_ID);

if (profilePersonalForm){
    profilePersonalForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        const object = {};
        const formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        const json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('updated personal data', response);
        });
    });
}

// basic js to update customer email
const ACC_PROFILE_EMAIL_ID = 'profileMailForm';
const profileMailForm = document.getElementById(ACC_PROFILE_EMAIL_ID);

if (profileMailForm){
    profileMailForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        const object = {};
        const formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        const json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('updated email address', response);
        });
    });
}

// basic js to update customer password
const ACC_PROFILE_PASSWORD_ID = 'profilePasswordForm';
const profilePasswordForm = document.getElementById(ACC_PROFILE_PASSWORD_ID);

if (profilePasswordForm){
    profilePasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        const object = {};
        const formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        const json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('updated password', response);
        });
    });
}
