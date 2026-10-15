// Regression tests for browser-local reward detection and audio opt-in.
// Run with: node --test tests/js/celebrations_test.cjs
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../amd/src/celebrations.js'), 'utf8')
    .replace(/^import .*;$/gm, '').replace('export const init', 'globalThis.init');
const setup = (storage = new Map(), states = {}, audio = {}, seasonyear = 2026) => {
    const listeners = {};
    const buttons = {};
    const button = {hidden: false, dataset: {}, setAttribute() {}, addEventListener(name, fn) {buttons[name] = fn;}};
    const toast = {hidden: true, textContent: '', animate() {}};
    const players = [];
    const context = {
        CourseEvents: {manualCompletionToggled: 'completion'},
        document: {hidden: false, addEventListener(name, fn) {listeners[name] = fn;}},
        window: {addEventListener() {}},
        localStorage: {
            getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value),
            removeItem: key => storage.delete(key), key: index => [...storage.keys()][index] ?? null,
            get length() {return storage.size;},
        },
        matchMedia: () => ({matches: true}), performance: {now: () => 10000},
        clearTimeout() {}, setTimeout: () => 1,
        Audio: class {
            constructor(url) {this.url = url; this.paused = true; players.push(this);}
            play() {this.paused = false; return Promise.resolve();}
            pause() {this.paused = true;}
        },
        AudioContext: class {
            state = 'suspended';
            resume() {this.state = 'running'; return Promise.resolve();}
            suspend() {this.state = 'suspended'; return Promise.resolve();}
        },
    };
    vm.createContext(context); vm.runInContext(source, context);
    const config = {storagekey: 'test', strings: {
        celebrateactivity: 'activity', celebratequiz: 'quiz', celebratecourse: 'course', celebratediscovery: 'discovery',
        soundon: 'on', soundoff: 'off',
    }, completionstates: states, audio, seasonyear};
    const controls = context.init({querySelector: selector => selector.includes('sound') ? button : toast}, config);
    return {controls, toast, listeners, storage, buttons, players, context, config};
};
test('first visit establishes baseline; new completion is celebrated once across visits', () => {
    const one = setup(undefined, {'activity:1': false, 'activity:2': true});
    one.controls.checkQuizzes(); assert.equal(one.toast.hidden, true);
    const two = setup(one.storage, {'activity:1': true, 'activity:2': true});
    two.controls.checkQuizzes(); assert.equal(two.toast.textContent, 'activity');
    const three = setup(one.storage, {'activity:1': true});
    three.controls.checkQuizzes(); assert.equal(three.toast.hidden, true);
});
test('course wins concurrent rewards and all are deduplicated', () => {
    const one = setup(undefined, {'activity:1': false, 'course:2': false});
    one.config.quizstates = {3: false}; one.controls.checkQuizzes();
    const two = setup(one.storage, {'activity:1': true, 'course:2': true});
    two.config.quizstates = {3: true}; two.controls.checkQuizzes();
    assert.equal(two.toast.textContent, 'course');
    const three = setup(one.storage, {'activity:1': true}); three.config.quizstates = {3: true};
    three.controls.checkQuizzes(); assert.equal(three.toast.hidden, true);
});
test('undo does not celebrate; discovery and manual completion share deduplication', () => {
    const state = setup();
    state.listeners.completion({detail: {completed: false, cmid: 7}}); assert.equal(state.toast.hidden, true);
    state.listeners.completion({detail: {completed: true, cmid: 7}}); assert.equal(state.toast.textContent, 'activity');
    state.controls.discover('star'); assert.equal(state.toast.textContent, 'discovery');
    state.toast.hidden = true; state.controls.discover('star'); assert.equal(state.toast.hidden, true);
    state.controls.setEnabled(false); state.controls.discover('bauble'); assert.equal(state.toast.hidden, true);
});
test('audio is opt-in, single-player, and stops on disable or hidden tab', async() => {
    const state = setup(undefined, {}, {activity: '/activity.mp3', star: '/star.mp3'});
    state.listeners.completion({detail: {completed: true, cmid: 7}}); assert.equal(state.players.length, 0);
    await state.buttons.click(); assert.equal(state.players.length, 0);
    state.listeners.completion({detail: {completed: true, cmid: 8}}); assert.equal(state.players.length, 1);
    state.controls.playGreeting('star', () => assert.fail('Unexpected fallback'));
    assert.equal(state.players[0].paused, true); assert.equal(state.players[1].paused, false);
    state.context.document.hidden = true; state.listeners.visibilitychange(); assert.equal(state.players[1].paused, true);
    state.context.document.hidden = false;
    state.controls.playGreeting('star', () => {}); state.controls.setEnabled(false);
    assert.equal(state.players[2].paused, true);
});
test('rewards persist across New Year within one season; sound choice persists across seasons', async() => {
    const december = setup(undefined, {'activity:1': false}, {activity: '/activity.mp3', discovery: '/discovery.mp3'});
    december.controls.checkQuizzes(); await december.buttons.click();
    assert.equal(december.storage.get('test:sound'), 'true');
    december.controls.discover('star');
    const january = setup(december.storage, {'activity:1': true});
    january.controls.checkQuizzes(); assert.equal(january.toast.textContent, 'activity');
    january.toast.hidden = true; january.controls.discover('star'); assert.equal(january.toast.hidden, true);
    const nextseason = setup(december.storage, {}, {}, 2027);
    nextseason.controls.discover('star'); assert.equal(nextseason.toast.hidden, false);
    assert.equal(nextseason.storage.get('test:sound'), 'true');
});
test('a new season removes rewards of earlier seasons but keeps the sound choice and other users', () => {
    const storage = new Map([
        ['test:sound', 'true'], ['test:rewards:2025:activity:1', 'true'], ['test:rewards:2026:activity:2', 'true'],
        ['other:rewards:2025:activity:1', 'true'],
    ]);
    setup(storage, {}, {}, 2026);
    assert.deepEqual([...storage.keys()].sort(),
        ['other:rewards:2025:activity:1', 'test:rewards:2026:activity:2', 'test:sound']);
});
