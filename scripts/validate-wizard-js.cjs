'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const path = 'plugin/bubba-hub-manager/includes/class-bh-activity-create.php';
const source = fs.readFileSync(path, 'utf8');
const match = source.match(/echo <<<'BH3_WIZARD_SCRIPT'\s*\r?\n([\s\S]*?)\r?\nBH3_WIZARD_SCRIPT;/);
assert.ok(match, 'Expected the activity wizard JavaScript heredoc');
new vm.Script(match[1], { filename: 'bh3-listing-wizard.js' });
assert.ok(match[1].includes('sessionRows'), 'Wizard should include timetable handling');
assert.ok(match[1].includes('bh3SavedListing'), 'Wizard should support editing saved drafts');
console.log('BH3 listing wizard JavaScript syntax and entry-point checks passed.');
