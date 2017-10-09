import Rx from 'rxjs/Rx';
import StateContainer from 'src/core/factory/state-container.factory';
import EventSystem from 'src/core/factory/event-system.factory';
import ApplicationState from 'src/app/service/application-state.service';

export default function initializeApplicationState(app, configuration, done) {
    const eventSystem = EventSystem(Rx);
    const stateContainer = StateContainer(eventSystem);

    configuration.eventSystem = eventSystem;
    configuration.stateContainer = stateContainer;
    configuration.applicationState = ApplicationState(stateContainer);

    app.state = configuration.applicationState;

    done(configuration);
}
