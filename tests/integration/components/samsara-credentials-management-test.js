import { module, test } from 'qunit';
import { setupRenderingTest } from 'dummy/tests/helpers';
import { render } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';

module('Integration | Component | samsara-credentials-management', function (hooks) {
    setupRenderingTest(hooks);

    test('it renders', async function (assert) {
        // Set any properties with this.set('myProperty', 'value');
        // Handle any actions with this.set('myAction', function(val) { ... });

        await render(hbs`<SamsaraCredentialsManagement />`);

        assert.dom().hasText('');

        // Template block usage:
        await render(hbs`
      <SamsaraCredentialsManagement>
        template block text
      </SamsaraCredentialsManagement>
    `);

        assert.dom().hasText('template block text');
    });
});
