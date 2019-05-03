import AdminWorker from 'src/core/worker/admin-worker.worker';
import WorkerNotificationListener from 'src/core/worker/worker-notification-listener';

let enabled = false;

/**
 * Starts the worker
 */
export default function initializeWorker() {
    const configService = this.getContainer('service').configService;
    const loginService = this.getContainer('service').loginService;
    const context = this.getContainer('init').contextService;

    if (loginService.isLoggedIn()) {
        configureWorker();
    } else {
        loginService.addOnLoginListener(configureWorker);
    }

    function configureWorker() {
        configService.getConfig()
            .then((response) => {
                if (response.adminWorker.enableAdminWorker && !enabled) {
                    enableAdminWorker(loginService, context, response.adminWorker);
                    enableWorkerNotificationListener(
                        loginService,
                        context
                    );
                }
            })
            .catch();
    }
}

function enableAdminWorker(loginService, context, config) {
    let worker = getWorker(loginService);

    if (loginService.isLoggedIn()) {
        worker.postMessage({
            context,
            bearerAuth: loginService.getBearerAuthentication(),
            host: window.location.origin,
            pollingConfig: config.pollInterval
        });
    }

    loginService.addOnTokenChangedListener((auth) => {
        worker.terminate();
        worker = getWorker(loginService);
        worker.postMessage({
            context,
            bearerAuth: auth,
            host: window.location.origin,
            pollingConfig: config.pollInterval
        });
    });

    loginService.addOnLogoutListener(() => {
        worker.terminate();
        worker = getWorker(loginService);
    });

    enabled = true;
}

function getWorker(loginService) {
    const worker = new AdminWorker();

    worker.onmessage = () => {
        loginService.refreshToken();
    };

    return worker;
}

function enableWorkerNotificationListener(loginService, context) {
    let workerNotificationListener = new WorkerNotificationListener(loginService, context);

    if (loginService.isLoggedIn()) {
        workerNotificationListener.start(5000);
    }

    loginService.addOnTokenChangedListener(() => {
        workerNotificationListener.terminate();
        workerNotificationListener = new WorkerNotificationListener(loginService, context);
        workerNotificationListener.start(5000);
    });

    loginService.addOnLogoutListener(() => {
        workerNotificationListener.terminate();
        workerNotificationListener = new WorkerNotificationListener(loginService, context);
    });
}
