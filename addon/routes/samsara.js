import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class SamsaraRoute extends Route {
    @service store;
    @service notifications;
    @service currentUser;

    beforeModel() {
        // Ensure user has access to Samsara extension
        if (!this.currentUser.hasPermission('samsara view')) {
            this.notifications.error('You do not have permission to access Samsara integration.');
            this.transitionTo('console');
        }
    }

    model() {
        return {
            credentials: this.store.findAll('samsara-credential'),
            vehicles: this.store.findAll('samsara-vehicle'),
            webhookEvents: this.store.query('samsara-webhook-event', {
                limit: 10,
                sort: '-created_at',
            }),
        };
    }

    setupController(controller, model) {
        super.setupController(controller, model);

        // Set up any additional controller properties
        controller.set('isLoading', false);
    }
}
