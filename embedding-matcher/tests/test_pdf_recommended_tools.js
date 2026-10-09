const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const renderedText = [];
let alerts = [];

function text(value) {
    return { nodeType: 3, nodeValue: value };
}

function element(tagName, children = [], selectors = {}) {
    const node = {
        nodeType: 1,
        tagName,
        childNodes: children,
        selectors,
        textContent: children.map((child) => child.nodeType === 3 ? child.nodeValue : child.textContent).join(''),
        classList: { contains: () => false },
        querySelector(selector) {
            return selectors[selector] || null;
        },
        querySelectorAll(selector) {
            return selectors[selector] || [];
        },
    };
    return node;
}

const courseTitleNode = element('DIV', [text('Introduction to Computing')]);
const recommendationStrong = element('STRONG', [text('Recommended tools/apps:')]);
const recommendationValue = element('SPAN', [
    text('Python or Java — Supports implementing programming exercises.'),
    element('BR'),
    text('Visual Studio Code — Supports editing and debugging code.'),
]);
const recommendations = element('DIV', [recommendationStrong, recommendationValue], {
    strong: recommendationStrong,
    span: recommendationValue,
    'a[href]': [],
});
const titleCell = element('TD', [courseTitleNode, recommendations], {
    '.course-title': courseTitleNode,
    '.course-description:not(.course-description-labeled)': null,
    '.course-topics': null,
    '.course-description-labeled': [recommendations],
    '.course-meta > span': [],
});
const rowCells = [
    element('TD', [text('BSIT-USER-01')]),
    titleCell,
    element('TD', [text('3')]),
];
const row = element('TR', [], { td: rowCells });
row.querySelectorAll = (selector) => selector === 'td' ? rowCells : [];
const table = element('TABLE', [], {
    'tbody tr': [row],
    'thead th': [],
});
table.querySelector = (selector) => selector === 'tbody tr' ? row : null;

const termHeading = element('STRONG', [text('First Term')]);
const term = element('DIV', [], {
    '.term-heading strong': termHeading,
    table,
});
term.querySelector = (selector) => term.selectors[selector] || null;
const yearHeading = element('H3', [text('Year 1')]);
const yearSection = element('SECTION', [], {
    '.year-heading': yearHeading,
    '.term table tbody tr': row,
    '.term': [term],
});
yearSection.querySelector = (selector) => yearSection.selectors[selector] || null;
yearSection.querySelectorAll = (selector) => yearSection.selectors[selector] || [];

const metadata = element('DIV');
const runNumber = element('SPAN', [text('Run #1')]);
const run = element('DETAILS', [], {
    '.run-header h2': element('H2', [text('Sample Draft')]),
    '.run-meta': metadata,
    '.draft-run-number': runNumber,
    '.curriculum > section': [yearSection],
});
run.querySelector = (selector) => run.selectors[selector] || null;
run.querySelectorAll = (selector) => run.selectors[selector] || [];
run.classList = { contains: () => false };

let clickHandler;
const button = {
    disabled: false,
    textContent: 'Export to PDF',
    closest: () => run,
    addEventListener: (_name, handler) => { clickHandler = handler; },
};
const pdf = {
    setProperties() {},
    splitTextToSize(value) { return String(value).split('\n'); },
    setFont() {},
    setFontSize() {},
    setTextColor() {},
    setFillColor() {},
    setDrawColor() {},
    setLineWidth() {},
    roundedRect() {},
    rect() {},
    circle() {},
    line() {},
    text(value) { renderedText.push(...(Array.isArray(value) ? value : [value])); },
    getTextWidth(value) { return String(value).length * 0.5; },
    getNumberOfPages() { return 1; },
    setPage() {},
    save() {},
};
const context = {
    Node: { ELEMENT_NODE: 1, TEXT_NODE: 3 },
    document: { querySelectorAll: () => [button] },
    window: {
        jspdf: { jsPDF: function () { return pdf; } },
        setTimeout: (callback) => callback(),
        alert: (message) => alerts.push(message),
    },
};

vm.runInNewContext(
    fs.readFileSync(path.join(__dirname, '..', 'assets', 'pdf-export.js'), 'utf8'),
    context,
);
clickHandler();

assert.equal(alerts.length, 0, alerts.join('\n'));
assert.ok(renderedText.some((line) => String(line).includes('BSIT-USER-01')));
assert.ok(renderedText.some((line) => String(line).includes('Python or Java')));
assert.ok(renderedText.some((line) => String(line).includes('Supports implementing programming exercises')));
console.log('PDF recommended-tools export smoke test passed.');
