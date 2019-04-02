import DomAccess from "../../helper/dom-access.helper";
import Client from "../../service/http-client.service";

const client = new Client(window.accessKey, window.contextToken);

// basic js to update customer data
const ACC_PROFILE_PERSONAL_ID = 'profilePersonalForm';
let profilePersonalForm = document.getElementById(ACC_PROFILE_PERSONAL_ID);

if (profilePersonalForm){
    profilePersonalForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        let object = {};
        let formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        let json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('updated personal data', response);
        });
    });
}

// basic js to update customer email
const ACC_PROFILE_EMAIL_ID = 'profileMailForm';
let profileMailForm = document.getElementById(ACC_PROFILE_EMAIL_ID);

if (profileMailForm){
    profileMailForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        let object = {};
        let formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        let json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('updated email address', response);
        });
    });
}

// basic js to update customer password
const ACC_PROFILE_PASSWORD_ID = 'profilePasswordForm';
let profilePasswordForm = document.getElementById(ACC_PROFILE_PASSWORD_ID);

if (profilePasswordForm){
    profilePasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        let object = {};
        let formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        let json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('updated password', response);
        });
    });
}
