'use strict';
const { buildEngine } = require('ember-engines/lib/engine-addon');
const { name } = require('./package');
const Funnel = require('broccoli-funnel');
const MergeTrees = require('broccoli-merge-trees');
const path = require('path');

module.exports = buildEngine({
    name,

    treeForPublic() {
        const publicTree = typeof this._super?.treeForPublic === 'function' ? this._super.treeForPublic.apply(this, arguments) : null;
        const trees = [];

        if (publicTree) {
            trees.push(publicTree);
        }

        trees.push(
            new Funnel(path.join(__dirname, 'assets'), {
                destDir: '/',
            })
        );

        return new MergeTrees(trees, { overwrite: true });
    },

    lazyLoading: {
        enabled: true,
    },

    isDevelopingAddon() {
        return true;
    },
});
