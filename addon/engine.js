import Engine from '@ember/engine';
import loadInitializers from 'ember-load-initializers';
import Resolver from 'ember-resolver';
import config from './config/environment';
import services from '@fleetbase/ember-core/exports/services';
import SamsaraSettingsComponent from './components/samsara-settings';
import SamsaraIconComponent from './components/samsara-icon';

const { modulePrefix } = config;
const externalRoutes = ['console', 'extensions'];
const FLEETOPS_ENGINE_NAME = '@fleetbase/fleetops-engine';

export default class SamsaraEngine extends Engine {
    modulePrefix = modulePrefix;
    Resolver = Resolver;
    dependencies = {
        services,
        externalRoutes,
    };
    engineDependencies = [FLEETOPS_ENGINE_NAME];
    setupExtension = function (app, engine, universe) {
        // Register Samsara Settings
        universe.registerMenuItem('engine:fleet-ops', 'Samsara', {
            component: SamsaraSettingsComponent,
            registerComponentToEngine: FLEETOPS_ENGINE_NAME,
            icon: 'gear',
            iconComponent: SamsaraIconComponent,
            slug: 'samsara',
            section: 'settings',
        });
    };
}

loadInitializers(SamsaraEngine, modulePrefix);
