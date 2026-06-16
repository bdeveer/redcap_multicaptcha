# Multi-CAPTCHA (REDCap External Module)

Protect public surveys with a bot-check / CAPTCHA service of your choice. Built as a
**provider-pluggable** module: an admin picks a provider globally, and individual
projects can override with their own provider and keys.

> **Status: scaffold / mock-up.** This is a working skeleton intended for a dev
> instance, not a vetted production release. Test each provider before relying on it.

## Why this exists

REDCap's built-in reCAPTCHA support is limited to **reCAPTCHA v2**, which is
increasingly defeated by bots and solver farms. This module lets you use modern,
low-friction or score-based alternatives instead.

## Supported providers

| Provider | Type | Keys needed |
|---|---|---|
| Cloudflare Turnstile | Invisible / managed challenge | Site key, Secret key |
| Google reCAPTCHA v3 | Invisible, score-based | Site key, Secret key, Score threshold |
| hCaptcha | Checkbox / invisible | Site key, Secret key |
| Friendly Captcha | Proof-of-work (EU/GDPR) | Site key, API key |
| mCaptcha | Self-hosted proof-of-work | Instance URL, Site key, Account secret |

## Getting keys for each provider

The same links appear in the module's configuration screen, next to each
provider's key fields.

| Provider | Get keys / sign up | Docs |
|---|---|---|
| Cloudflare Turnstile | [Cloudflare dashboard → Turnstile](https://dash.cloudflare.com/?to=/:account/turnstile) | [Setup docs](https://developers.cloudflare.com/turnstile/get-started/) |
| Google reCAPTCHA v3 | [reCAPTCHA admin console](https://www.google.com/recaptcha/admin/create) (choose **v3**) | [v3 docs](https://developers.google.com/recaptcha/docs/v3) |
| hCaptcha | [hCaptcha dashboard](https://dashboard.hcaptcha.com/signup) | [Developer docs](https://docs.hcaptcha.com/) |
| Friendly Captcha | [Getting started](https://developer.friendlycaptcha.com/docs/getting-started/client) | [Developer hub](https://developer.friendlycaptcha.com/) |
| mCaptcha | self-hosted — create a sitekey on your instance | [mCaptcha docs](https://mcaptcha.org/docs/) |

Turnstile, reCAPTCHA, and hCaptcha all publish always-pass / always-fail **test
keys** you can use to exercise both paths without a real account.

## How it works (the gate pattern)

REDCap has no clean hook to *reject a survey submission*. So this module — like
Günther Rezniczek's `nedCAPTCHA`, which it's modeled on — doesn't gate the
submission. It gates **entry** to the survey:

1. `redcap_survey_page_top` fires before the survey renders.
2. If this is the first page of the **public** survey and the visitor hasn't
   passed yet, the module prints its own challenge page and calls
   `exitAfterHook()` — so REDCap never renders the survey.
3. The challenge form POSTs back to the same URL. The module reads the provider's
   token and verifies it **server-side** against the provider's API.
4. On success it sets a session flag, then redirects to a clean GET so the real
   survey renders. A session flag means the visitor isn't re-challenged on every
   page (unless "always require" is enabled).

Only two things differ per provider — **render the widget** and **verify the
token** — so adding a new service is one class plus one line in the factory.

## File layout

```
multicaptcha_em/
├── config.json                       System + project settings (provider dropdowns, keys)
├── MultiCaptchaExternalModule.php    The gate logic (provider-agnostic)
├── ui.html                           Interstitial challenge page template
└── classes/
    ├── CaptchaProvider.php           Abstract base (4 methods + shared cURL helper)
    ├── ProviderFactory.php           id -> provider class
    ├── TurnstileProvider.php
    ├── RecaptchaV3Provider.php
    ├── HcaptchaProvider.php
    ├── FriendlyCaptchaProvider.php
    └── McaptchaProvider.php
```

## Install (dev instance)

1. Copy this folder to `<redcap-root>/modules/multicaptcha_v0.1.0`
   (the `_vX.Y.Z` suffix is required by the EM framework).
2. Edit the `authors` block in `config.json`.
3. Control Center → External Modules → enable **Multi-CAPTCHA**.
4. Set a global provider + keys in the module's system configuration.
5. Enable on a project; optionally tick **Override global settings** to use a
   different provider/keys for that project.
6. Test with the project's **public survey** link.

## Adding a new provider

1. Create `classes/MyProvider.php` extending `CaptchaProvider`; implement
   `getId`, `getLabel`, `getScriptHtml`, `getWidgetHtml`, `getResponseField`,
   `verify`.
2. Add a `case` to `ProviderFactory::create()`.
3. Add the option + key fields to both `system-settings` and `project-settings`
   in `config.json`.

## Known caveats / TODO

- **mCaptcha** widget markup and the `siteverify` payload vary by version —
  confirm against the instance you deploy.
- **Friendly Captcha** here targets the **v1** endpoint; v2 sites use
  `global.frcapi.com` + an `X-API-Key` header.
- The 8-second submit-enable safety net in `ui.html` prevents lockout if a
  provider callback never fires; server-side verification still gates entry.
- Consider rate-limiting / logging failed attempts if abuse is a concern.

## Credit

Gate strategy modeled on **nedCAPTCHA** by Günther A. Rezniczek
(<https://github.com/grezniczek/redcap_nedcaptcha>), MIT-licensed.
