import HttpClient from "../../service/http-client.service";
import DomAccess from "../../helper/dom-access.helper";

const client = new HttpClient(window.accessKey, window.contextToken);

// basic js to log in an existing user
let loginForm = document.getElementById('loginForm');
if (loginForm){
    loginForm.addEventListener('submit', function(e) {
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

            let obj = JSON.parse(response);

            if (!obj.errors) {
                location.reload();
            }
        });
    });
}
