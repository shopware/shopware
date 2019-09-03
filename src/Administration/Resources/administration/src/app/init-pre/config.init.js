export default function initializeConfigDecorator() {
    return new Promise((resolve) => {
        this.addInitializerDecorator('worker', (service) => {
            const configService = this.getContainer('service').configService;
            const loginService = this.getContainer('service').loginService;
            const context = this.getContainer('service').context;

            function getConfig() {
                return configService.getConfig().then((response) => {
                    context.config = response;
                    service.then((configureWorker) => resolve(configureWorker()));
                });
            }
            if (loginService.isLoggedIn()) {
                getConfig().catch();
                return;
            }

            loginService.addOnLoginListener(getConfig);
            resolve();
        });
    });
}
