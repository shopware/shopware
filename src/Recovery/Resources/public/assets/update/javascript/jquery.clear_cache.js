(function($) {

    var clearCacheProcess = {

        options: {
            counterSelector: '.counter',
            formSelector: 'form[name=cleanupForm]',
            buttonSelector: '.startCleanUpProcess',
            spinnerSelector: '.clearCacheSpinner',
            counterContainerSelector: '.fileCounterContainer',
            errorContainerSlector: '.error-message-container',
            urlAttribute: 'data-clearCacheUrl'
        },

        /**
         * init the cache clear object
         */
        init: function() {
            var me = this;

            me.createProperties();
            me.registerEvents();
        },

        /**
         * registers all properties
         */
        createProperties: function() {
            var me = this;

            me.$counterMonitor = $(me.options.counterSelector);
            me.$form = $(me.options.formSelector);
            me.$startButton = $(me.options.buttonSelector);
            me.$spinner = $(me.options.spinnerSelector);
            me.$counterContainer = $(me.options.counterContainerSelector);
            me.$errorContainer = $(me.options.errorContainerSlector);
            me.url = me.$startButton.attr(me.options.urlAttribute);
            me.deletedFiles = 0;
        },

        /**
         * registers required events
         */
        registerEvents: function() {
            var me = this;

            me.$startButton.on('click', $.proxy(me.onStartButtonClick, me));
        },

        /**
         * on Click event handler
         */
        onStartButtonClick: function() {
            var me = this;

            me.$counterContainer.show();
            me.$spinner.show();
            me.$startButton.attr('disabled', true);
            me.deleteCacheProcess();
        },

        /**
         * updates the view with the current deleted files
         */
        updateFileCounter: function() {
            var me = this;

            me.$counterMonitor.html(me.deletedFiles);
        },

        /**
         * after a second submits the form
         */
        submitForm: function() {
            var me = this;

            window.setTimeout(function() {
                me.$form.submit();
            }, 1000);
        },

        /**
         * recursive delete the cache
         */
        deleteCacheProcess: function() {
            var me = this;

            $.ajax({
                url: me.url,
                method: 'POST',
                success: $.proxy(me.proceedCacheProcess, me),
                failure: $.proxy(me.onFailure, me)
            });
        },

        /**
         * Continues the clear cache process while its not ready.
         * If ready continue the cleanup process by submit the form.
         *
         * @param {string} response
         */
        proceedCacheProcess: function(response) {
            var me = this;

            response = $.parseJSON(response);

            me.deletedFiles += response.deletedFiles;
            me.updateFileCounter();

            if (response.ready) {
                me.submitForm();
                return;
            }

            me.deleteCacheProcess();
        },

        /**
         * On failure show a message with a hint to delete the cache manual
         * and continue to the clean up process by submit the form.
         */
        onFailure: function() {
            var me = this;

            me.$errorContainer.show();
            me.submitForm();
        }
    };

    // init clear cache process
    clearCacheProcess.init();

})(jQuery);
