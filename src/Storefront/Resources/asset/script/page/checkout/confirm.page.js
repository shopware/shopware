import Client from "../../service/http-client.service";
import DomAccess from "../../helper/dom-access.helper";
import ButtonLoadingIndicator from "../../plugin/loading-indicator/button-loading-indicator.plugin";

const client = new Client(window.accessKey, window.contextToken);

let confirmForm = document.getElementById('confirmForm');

if (confirmForm){
    confirmForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.srcElement;
        const requestUrl = DomAccess.getAttribute(form, 'action');
        const finishUrl = DomAccess.getAttribute(form, 'data-finish-url');

        let loader = new ButtonLoadingIndicator(
            form.querySelector('button[type="submit"]')
        );
        loader.create();

        client.post(requestUrl.toLowerCase(), JSON.stringify({}), (response) => {
            let obj = JSON.parse(response);

            if (obj.data.id) {
                window.location = finishUrl + '?orderId=' + obj.data.id;
            }

            loader.remove();
        });
    });
}
