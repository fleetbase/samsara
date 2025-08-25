import ApplicationAdapter from '@fleetbase/ember-core/adapters/application';

export default class SamsaraAdapter extends ApplicationAdapter {
    namespace = 'samsara/int/v1';

    pathForType() {
        const path = super.pathForType(...arguments);
        return path.replace('samsara-', '');
    }
}
