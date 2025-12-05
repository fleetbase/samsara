import { MenuItem, ExtensionComponent } from '@fleetbase/ember-core/contracts';

export default {
    setupExtension(app, universe) {
        const menuService = universe.getService('menu');

        // Register settings into fleetops
        universe.registerMenuItem('engine:fleet-ops', new MenuItem({
            title: 'Samsara',
            component: new ExtensionComponent('@fleetbase/samsara-engine', 'samsara-settings'),
            icon: 'gear',
            iconComponent: new ExtensionComponent('@fleetbase/samsara-engine', 'samsara-icon'),
            slug: 'samsara',
            section: 'settings',
        }));
    },
};
