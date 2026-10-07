const encoder = new TextEncoder();

const hasRequiredDifficulty = (hash, difficulty) => {
  let remainingBits = difficulty;

  for (const byte of new Uint8Array(hash)) {
    if (remainingBits <= 0) {
      return true;
    }

    const bitsToCheck = Math.min(remainingBits, 8);
    if ((byte >> (8 - bitsToCheck)) !== 0) {
      return false;
    }

    remainingBits -= bitsToCheck;
  }

  return true;
};

const solveChallenge = async (challenge, difficulty) => {
  for (let solution = 0; ; ++solution) {
    const hash = await window.crypto.subtle.digest('SHA-256', encoder.encode(`${challenge}|${solution}`));

    if (hasRequiredDifficulty(hash, difficulty)) {
      return String(solution);
    }
  }
};

const findFields = (form) => ({
  token: form.querySelector('[data-spam-protection-token]'),
  challenge: form.querySelector('[data-spam-protection-challenge]'),
  solution: form.querySelector('[data-spam-protection-solution]'),
});

const hasPendingChallenge = (form) => {
  const { challenge, solution } = findFields(form);

  return Boolean(challenge && solution && challenge.value && !solution.value);
};

/**
 * For forms submitted in JavaScript (fetch), which must solve the challenge before building their payload.
 */
export const solveSpamProtection = async (form) => {
  if (!hasPendingChallenge(form)) {
    return;
  }

  const { challenge, solution } = findFields(form);
  solution.value = await solveChallenge(challenge.value, Number(challenge.dataset.spamProtectionDifficulty));
};

/**
 * Tokens are single-use: a form submitted in JavaScript must load the fresh ones sent back after a failure.
 */
export const refreshSpamProtection = (form, { renderedAt, challenge, difficulty }) => {
  const fields = findFields(form);

  if (!fields.token || !fields.challenge || !fields.solution) {
    return;
  }

  fields.token.value = renderedAt;
  fields.challenge.value = challenge;
  fields.challenge.dataset.spamProtectionDifficulty = difficulty;
  fields.solution.value = '';
};

document.addEventListener('submit', async (event) => {
  const form = event.target;

  // A form handling its own submission (e.g. through fetch) calls solveSpamProtection itself.
  if (event.defaultPrevented || !hasPendingChallenge(form)) {
    return;
  }

  event.preventDefault();
  const submitter = event.submitter;
  const previousDisabledState = submitter?.disabled;

  if (submitter) {
    submitter.disabled = true;
  }

  await solveSpamProtection(form);

  if (submitter) {
    submitter.disabled = previousDisabledState;
  }

  // A challenge can be solved before the browser has finished dispatching the submit event, and the browser ignores
  // a submission requested meanwhile: submit again from a new task.
  setTimeout(() => form.requestSubmit(submitter));
});
