/**
 * @package innovation
 *
 * @experimental stableVersion:v6.7.0 feature:SPATIAL_BASES
 */
export default class Overlay {

    private overlay: HTMLElement;
    private exitButton: HTMLElement;
    private progressBar: HTMLElement;

    private progress = 0;

    static options = {
        overlay: '[data-spatial-ar-overlay]',
        exitButton: '[data-spatial-ar-overlay-exit]',
        progressBar: '[data-spatial-ar-overlay-progress]',
        classes: {
            visible: 'is--visible',
            loading: 'is--loading',
            placementHint: 'is--placement-hint',
            tracking: 'is--tracking',
            sessionRunning: 'is--session-running',
        },
        placementHintTimeout: 3000,
    };

    constructor(overlay: HTMLElement) {
        this.overlay = overlay;

        this.overlay.classList.add( Overlay.options.classes.visible );
        this.overlay.classList.add( Overlay.options.classes.loading );
        this.overlay.classList.add( Overlay.options.classes.placementHint );

        this.exitButton = this.overlay.querySelector( Overlay.options.exitButton );
        this.progressBar = this.overlay.querySelector( Overlay.options.progressBar );
        this.startProgress();
    }

    public sessionStarted(): void {
        this.overlay.classList.remove( Overlay.options.classes.loading );
        this.overlay.classList.add( Overlay.options.classes.sessionRunning );
    }

    public sessionEnded(): void {
        this.overlay.classList.remove( Overlay.options.classes.sessionRunning );
        this.overlay.classList.remove( Overlay.options.classes.visible );
        this.overlay.classList.remove( Overlay.options.classes.loading );
        this.overlay.classList.remove( Overlay.options.classes.placementHint );
        this.overlay.classList.remove( Overlay.options.classes.tracking );
    }

    public trackingStarted(): void {
        this.overlay.classList.add( Overlay.options.classes.tracking );
    }

    public get element(): HTMLElement {
        return this.overlay;
    }

    /**
     * adds a listener to the exit button
     * @param callback
     */
    public addExitListener( callback: () => void ): void {
        this.exitButton.addEventListener( 'click', callback );
    }

    /**
     * removes a listener from the exit button
     * @param callback
     */
    public removeExitListener( callback: () => void ): void {
        this.exitButton.removeEventListener( 'click', callback );
    }

    /**
     * Starts the progress bar
     *
     * Has a fixed to time just so the placement hint is visible for enough time to read
     * @private
     */
    private startProgress(): void {
        this.progress = 0;
        const interval = setInterval( () => {
            this.progress += 1;
            this.progressBar.style.width = `${this.progress}%`;
            this.progressBar.setAttribute( 'aria-valuenow', `${this.progress}` );
            if ( this.progress >= 100 ) {
                clearInterval( interval );
                this.overlay.classList.remove( Overlay.options.classes.placementHint );
            }
        }, Overlay.options.placementHintTimeout / 100 );
    }
}
