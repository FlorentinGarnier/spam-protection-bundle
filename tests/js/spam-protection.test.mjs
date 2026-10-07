import assert from 'node:assert/strict';
import { test } from 'node:test';

// Minimal browser: a hash made of zeros solves any challenge, without leaving the microtask queue.
const listeners = [];
globalThis.document = { addEventListener: (type, listener) => type === 'submit' && listeners.push(listener) };
globalThis.window = { crypto: { subtle: { digest: async () => new ArrayBuffer(32) } } };

await import('../../assets/spam-protection.js');

const drainMicrotasks = async () => {
  for (let i = 0; i < 1000; ++i) {
    await null;
  }
};

const createForm = () => {
  const fields = {
    '[data-spam-protection-token]': { value: 'token', dataset: {} },
    '[data-spam-protection-challenge]': { value: 'challenge', dataset: { spamProtectionDifficulty: '10' } },
    '[data-spam-protection-solution]': { value: '', dataset: {} },
  };
  const form = {
    dispatchingSubmitEvent: false,
    submissions: [],
    querySelector: (selector) => fields[selector] ?? null,
    // Like browsers, ignores a submission requested while the submit event is still being dispatched.
    requestSubmit(submitter) {
      if (!this.dispatchingSubmitEvent) {
        this.submissions.push({ submitter, solution: fields['[data-spam-protection-solution]'].value });
      }
    },
  };

  return form;
};

// Browsers run the microtasks after each listener, before the dispatch ends.
const dispatchSubmit = async (form, submitter) => {
  const event = {
    target: form,
    submitter,
    defaultPrevented: false,
    preventDefault() {
      this.defaultPrevented = true;
    },
  };

  form.dispatchingSubmitEvent = true;
  for (const listener of listeners) {
    listener(event);
    await drainMicrotasks();
  }
  form.dispatchingSubmitEvent = false;

  return event;
};

test('it submits the form again once the challenge is solved', async () => {
  const form = createForm();
  const submitter = { disabled: false };

  const event = await dispatchSubmit(form, submitter);
  await new Promise((resolve) => setTimeout(resolve, 10));

  assert.equal(event.defaultPrevented, true);
  assert.equal(form.submissions.length, 1);
  assert.equal(form.submissions[0].submitter, submitter);
  assert.notEqual(form.submissions[0].solution, '');
  assert.equal(submitter.disabled, false);
});

test('it lets a form whose challenge is solved be submitted', async () => {
  const form = createForm();
  form.querySelector('[data-spam-protection-solution]').value = '42';

  const event = await dispatchSubmit(form, null);

  assert.equal(event.defaultPrevented, false);
  assert.equal(form.submissions.length, 0);
});
