import HttpClient from "../../service/http-client.service";
import DomAccess from "../../helper/dom-access.helper";

const client = new HttpClient(window.accessKey, window.contextToken);

// basic js for registering a new user // TODO: NEXT-2077 - refactor
let registerForm = document.getElementById('registerForm');
if (registerForm){
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.srcElement;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        let object = {};
        let formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        let json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('register', response);
        });
    });
}

// basic js to log in an existing user // TODO: NEXT-2077 - refactor
let loginForm = document.getElementById('loginForm');
if (loginForm){
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.srcElement;
        const requestUrl = DomAccess.getAttribute(form, 'action');

        let object = {};
        let formData = new FormData(form);
        formData.forEach(function(value, key){
            object[key] = value;
        });
        let json = JSON.stringify(object);

        client.post(requestUrl.toLowerCase(), json, (response) => {
            console.log('login', response);

            let obj = JSON.parse(response);

            if (!obj.errors) {
                location.reload();
            }
        });
    });
}
