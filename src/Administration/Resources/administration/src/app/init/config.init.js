export default function initializeConfigDecorator() {
    const configService = this.getContainer('service').configService;
    const loginService = this.getContainer('service').loginService;
    const context = this.getContainer('init').contextService;

    this.addInitializerDecorator('worker', (service) => {
        function getConfig() {
            return configService.getConfig().then((response) => {
                context.config = response;
                service();
            });
        }
        if (loginService.isLoggedIn()) {
            getConfig().catch();
            return;
        }

        loginService.addOnLoginListener(getConfig);
    });
}
