
const GOOGLE_API_JS_URL = 'https://apis.google.com/js/api.js';
const GOOGLE_DISCOVERY_DOCS_URL = 'https://www.googleapis.com/discovery/v1/apis/drive/v3/rest';

export default class GoogleAuthenService {
    constructor() {
        this.googleAuth = null;
        this.isInit = false;
        this.prompt = null;
    }

    get isAuthorized() {
        if (!this.googleAuth) {
            return false;
        }
        return this.googleAuth.isSignedIn.get();
    }

    installClient() {
        const apiUrl = GOOGLE_API_JS_URL;
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = apiUrl;
            script.onload = () => {
                if (!script.readyState || /loaded|complete/.test(script.readyState)) {
                    setTimeout(() => {
                        resolve();
                    }, 500);
                }
            };
            script.onreadystatechange = script.onload;
            document.getElementsByTagName('head')[0].appendChild(script);
        });
    }

    initClient(config) {
        return new Promise((resolve) => {
            window.gapi.load('auth2', () => {
                window.gapi.auth2.init(config)
                    .then(() => {
                        resolve(window.gapi);
                    });
            });
        });
    }

    load(options) {
        // Default config and Prompt
        let googleAuthConfig = null;
        const googleAuthDefaultConfig = {
            scope: 'profile email',
            discoveryDocs: [GOOGLE_DISCOVERY_DOCS_URL]
        };
        let prompt = 'select_account';
        if (typeof options === 'object') {
            googleAuthConfig = Object.assign(googleAuthDefaultConfig, options);
            if (options.scope) {
                googleAuthConfig.scope = options.scope;
            }
            if (options.prompt) {
                prompt = options.prompt;
            }
        } else {
            console.error('invalid option type. Object type accepted only');
        }
        this.installClient()
            .then(() => {
                return this.initClient(googleAuthConfig);
            })
            .then((gapi) => {
                this.googleAuth = gapi.auth2.getAuthInstance();
                this.isInit = true;
                this.prompt = prompt;
            });
    }

    getAuthCode(successCallback, errorCallback) {
        return new Promise((resolve, reject) => {
            if (!this.googleAuth) {
                if (typeof errorCallback === 'function') {
                    errorCallback(false);
                }
                reject();
                return;
            }
            this.googleAuth.grantOfflineAccess({ prompt: this.prompt })
                .then((response) => {
                    if (typeof successCallback === 'function') {
                        successCallback(response.code);
                    }
                    resolve(response.code);
                })
                .catch((error) => {
                    if (typeof errorCallback === 'function') {
                        errorCallback(error);
                        return;
                    }
                    reject(error);
                });
        });
    }

    signIn(successCallback, errorCallback) {
        return new Promise((resolve, reject) => {
            if (!this.googleAuth) {
                if (typeof errorCallback === 'function') {
                    errorCallback(false);
                }
                reject();
                return;
            }
            this.googleAuth.signIn()
                .then((googleUser) => {
                    if (typeof successCallback === 'function') {
                        successCallback(googleUser);
                    }
                    resolve(googleUser);
                })
                .catch((error) => {
                    if (typeof errorCallback === 'function') {
                        errorCallback(error);
                        return;
                    }
                    reject(error);
                });
        });
    }

    signOut(successCallback, errorCallback) {
        return new Promise((resolve, reject) => {
            if (!this.googleAuth) {
                if (typeof errorCallback === 'function') {
                    errorCallback(false);
                }
                reject();
                return;
            }
            this.googleAuth.signOut()
                .then(() => {
                    if (typeof successCallback === 'function') {
                        successCallback();
                    }
                    resolve(true);
                })
                .catch((error) => {
                    if (typeof errorCallback === 'function') {
                        errorCallback(error);
                        return;
                    }
                    reject(error);
                });
        });
    }
}
