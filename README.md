# Spam Protection Bundle

[![CI](https://github.com/FlorentinGarnier/spam-protection-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/FlorentinGarnier/spam-protection-bundle/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/florentingarnier/spam-protection-bundle.svg)](https://packagist.org/packages/florentingarnier/spam-protection-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/florentingarnier/spam-protection-bundle.svg)](https://packagist.org/packages/florentingarnier/spam-protection-bundle)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)
![Symfony](https://img.shields.io/badge/symfony-5.4%20%7C%206.4%20%7C%207.4%20%7C%208-black.svg)

Symfony integration of [florentingarnier/spam-protection](https://github.com/FlorentinGarnier/spam-protection),
an invisible CAPTCHA alternative: a honeypot, single-use timed tokens, a proof of work solved by the browser,
per-form rate limiting, IP reputation and gibberish detection. No third-party service, no puzzle for your
visitors.

The bundle provides:

- `SpamProtectionType`, a form type you add to any form;
- a JavaScript solver for the proof of work;
- the `spam-protection:refresh-ip-lists` console command, which downloads the datacenter, VPN and Tor lists;
- a form theme, translations (English, French, German) and a dedicated Monolog channel.

Using Sylius? See [florentingarnier/sylius-spam-protection-plugin](https://github.com/FlorentinGarnier/sylius-spam-protection-plugin).

## Requirements

- PHP 8.2 or later
- Symfony 5.4, 6.4, 7.4 or 8.x, with the HTTP client enabled (`framework.http_client`). Symfony 8 requires
  PHP 8.4.
- A cache pool shared by all your web servers
- JavaScript in the visitor's browser

## Installation

```bash
composer require florentingarnier/spam-protection-bundle
```

If you do not use Symfony Flex, enable the bundle:

```php
// config/bundles.php
return [
    // ...
    FlorentinGarnier\SpamProtectionBundle\FlorentinGarnierSpamProtectionBundle::class => ['all' => true],
];
```

## Configuration

Every option is optional. The defaults are:

```yaml
# config/packages/florentin_garnier_spam_protection.yaml
florentin_garnier_spam_protection:
    # Signs the tokens and hashes the IP addresses written to the logs.
    secret: '%kernel.secret%'
    # PSR-6 pool keeping used tokens and attempt counters; it must be shared by every web server.
    cache_pool: cache.app
    # Leading zero bits required from the proof of work of a first submission (1 to 20).
    base_difficulty: 10
    # Weighted attempts allowed per form and IP address before submissions are rejected.
    maximum_attempts_per_hour: 20
    ip_reputation:
        # Compiled lists, written by the console command and read by every web server.
        list_path: '%kernel.project_dir%/var/spam_protection/ip_reputation.php'
        # URLs of plain-text IPv4 / CIDR lists, per risk level.
        sources:
            hosting:
                - 'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/datacenter/ipv4.txt'
                - 'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/output/vpn/ipv4.txt'
            tor:
                - 'https://check.torproject.org/torbulkexitlist'
```

Run `bin/console config:dump-reference florentin_garnier_spam_protection` to display this reference.

## Usage

### 1. Add the field to a form

```php
use FlorentinGarnier\SpamProtectionBundle\Form\SpamProtectionType;

$builder->add('spam_protection', SpamProtectionType::class, [
    'protection_scope' => 'quotation',
    'content_fields' => ['message'],
]);
```

| Option | Default | Description |
|--------|---------|-------------|
| `protection_scope` | `'unknown'` | Identifies the form: tokens and rate limits are not shared between forms. Give each form its own scope. |
| `content_fields` | `[]` | Sibling fields whose free text is rejected when made of random characters. |

The field is not mapped. When a submission is rejected, the error `florentin_garnier_spam_protection.invalid`
is added to the parent form, so it shows up with the global form errors.

### 2. Render it

The bundle registers a form theme: `form_row()` (and `form_rest()`) renders the fields inside a block that is
hidden from both sighted users and screen readers.

```twig
{{ form_row(form.spam_protection) }}
```

If you render it with `form_widget()`, wrap it yourself in an element with `hidden` and `aria-hidden="true"`.

### 3. Load the JavaScript solver

`assets/spam-protection.js` is an ES module. Once imported, it solves the proof of work of every protected form
on the page when the form is submitted. It has no dependency.

With Webpack Encore, declare an alias and import it from your entry point:

```js
// webpack.config.js
const path = require('path');

Encore.addAliases({
  '@spam-protection': path.resolve(__dirname, 'vendor/florentingarnier/spam-protection-bundle/assets/spam-protection.js'),
});
```

```js
// assets/app.js
import '@spam-protection';
```

### Forms submitted with JavaScript

The solver ignores submissions whose default action was already prevented. A form sent with `fetch()` must solve
the challenge itself before building its payload:

```js
import { refreshSpamProtection, solveSpamProtection } from '@spam-protection';

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  await solveSpamProtection(form);

  const response = await fetch(form.action, { method: 'POST', body: new FormData(form) });
  const json = await response.json();

  if (!response.ok && json.spamProtection) {
    refreshSpamProtection(form, json.spamProtection);
  }
});
```

Tokens are single-use, so an error response must send fresh ones back. `ChallengeRefresh` builds the payload
expected by `refreshSpamProtection()`:

```php
use FlorentinGarnier\SpamProtectionBundle\Form\ChallengeRefresh;

return new JsonResponse([
    'error' => $message,
    'spamProtection' => ChallengeRefresh::fromFormView($form->createView()['spam_protection']),
], 422);
```

## IP reputation lists

```bash
bin/console spam-protection:refresh-ip-lists
```

The command downloads every source and replaces the compiled lists atomically. If a source is unavailable or
returns no usable range, the command fails and the previous lists are kept.

- Run it once after each deployment. Until the file exists, every address is considered `normal`.
- Schedule it daily, for example with cron:

  ```cron
  17 4 * * * cd /path/to/project && php bin/console spam-protection:refresh-ip-lists
  ```

- `list_path` must be readable by your web servers. If the command runs on another machine or container, share
  the directory.

The default datacenter and VPN lists come from [X4BNet/lists_vpn](https://github.com/X4BNet/lists_vpn), which
does not declare a license: they are downloaded at runtime and never distributed with this bundle. Replace them
in `sources` if needed.

## Logging

When MonologBundle is installed, every submission is logged on the `spam_protection` channel:

| Event | Level | Context |
|-------|-------|---------|
| `spam_protection.accepted` | info | `form`, `method`, `path`, `ip_hash`, `user_agent_hash`, `ip_risk` |
| `spam_protection.rejected` | warning | the same, plus `reason` |

IP addresses and user agents are hashed with HMAC-SHA256 and `secret`: they cannot be read back, but the same
visitor always gets the same hash. To write the channel to its own file:

```yaml
# config/packages/prod/monolog.yaml
monolog:
    handlers:
        spam_protection:
            type: rotating_file
            path: '%kernel.logs_dir%/spam_protection.log'
            level: info
            max_files: 90
            channels: ['spam_protection']
```

## Translations

The error message `florentin_garnier_spam_protection.invalid` is translated in English, French and German, in
the `messages` domain. Override it in your application's translation files.

## Production checklist

- [ ] **Trusted proxies.** Behind a reverse proxy, configure `framework.trusted_proxies`. Otherwise every visitor
  shares the proxy's IP address and the rate limit applies to everyone. If the logs always show the same
  `ip_hash`, this is the cause.
- [ ] **Shared cache.** With several web servers, `cache_pool` must use a shared storage such as Redis.
- [ ] **IP lists.** The command runs after the deployment and every day.

## Testing

```bash
composer install
vendor/bin/phpunit
```

To test your own protected forms, use `SpamProtectionTestHelper` from the
[library](https://github.com/FlorentinGarnier/spam-protection#testing-your-own-forms).

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) and the
[Code of Conduct](CODE_OF_CONDUCT.md). Report security issues privately, as
described in [SECURITY.md](SECURITY.md).

## License

Released under the [MIT License](LICENSE).
