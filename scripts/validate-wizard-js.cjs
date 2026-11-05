'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const path = 'plugin/bubba-hub-manager/includes/class-bh-activity-create.php';
const source = fs.readFileSync(path, 'utf8');
const match = source.match(/wp_add_inline_script\(\s*'bh3-activity-wizard'\s*,\s*<<<'BH3_WIZARD_SCRIPT'\s*\r?\n([\s\S]*?)\r?\nBH3_WIZARD_SCRIPT\s*\)/);
assert.ok(match, 'Expected the activity wizard JavaScript heredoc');
const javascript = match[1];
new vm.Script(javascript, { filename: 'bh3-listing-wizard.js' });
assert.ok(match[1].includes('sessionRows'), 'Wizard should include timetable handling');
assert.ok(source.includes('const bh3SavedListing=') && source.includes('const bh3SavedSessions='), 'Wizard should inject saved listing and session data before the inline script');
console.log('BH3 listing wizard JavaScript syntax and entry-point checks passed.');
