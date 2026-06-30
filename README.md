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

## Choosing a provider

All four do the same job — keep bots off your public surveys — but they differ on
cost, how much they bother the participant, and what they do with participant data.
That last point matters for research: hosted providers load third-party JavaScript
in the participant's browser and verify tokens by calling out from your REDCap
server, so they are a data-flow decision, not just a config toggle.

*Pricing and terms below were current as of mid-2026 — confirm against each
provider's own page before committing, as these change.*

| Provider | Cost | Participant friction | Privacy / regulatory | Practical notes |
|---|---|---|---|---|
| **Cloudflare Turnstile** | Free, effectively unlimited | Invisible (usually no click) | GDPR/CCPA/ePrivacy compliant; **no cookie-consent banner needed**; no PII tracking. Traffic transits Cloudflare's global network, so strict **EU data residency** needs enterprise data-localization. | Easiest all-round choice; quickest setup. |
| **Google reCAPTCHA v3** | Free only to **10,000 assessments/month per org** (cut in 2025); then ~$8/mo to 100k, ~$1 per 1,000 after | Invisible, score-based | Sends data to Google (US transfer); in the EU **typically requires a consent banner**. Weakest privacy posture of the four. | Most familiar/documented, but the score needs threshold tuning and the privacy story is the hardest to defend for human-subjects data. |
| **hCaptcha** | Free to **100,000 verifications/month**; Pro ≈ $99–139/mo | Often a visible click/challenge (more friction than the invisible options) | Privacy-focused, no PII collected; works in essentially every country. | Good free headroom; expect occasional visible challenges for participants. |
| **Friendly Captcha** | Free tier for non-commercial; paid from ≈ €9/mo (Starter, 1k req) / €39/mo (Growth, 5k req); **EU-only endpoint needs the ≈ €200/mo Advanced plan** | Invisible (proof-of-work) | German company, EU servers, GDPR/CCPA, **no cookies/no banner**, accessibility-compliant (WCAG/EAA). Strongest fit for EU/GDPR among these options. | Uses the Friendly Captcha **v2** API (current). For EU data residency you can point it at the EU endpoint (see caveats). |

### Quick guidance

- **Just want it to work, low fuss:** Cloudflare Turnstile — free, invisible, no consent banner.
- **EU participants / strict GDPR:** Friendly Captcha (EU-hosted); Turnstile is also defensible (no banner). Avoid reCAPTCHA.
- **No third party may touch participant data, or the server is firewalled/air-gapped:** none of these four fits (they all call out to a hosted service). Use the local-only [nedCAPTCHA](https://github.com/grezniczek/redcap_nedcaptcha) (image/math, no external service), or a fully self-hosted option like mCaptcha — this module is for when you specifically want a modern third-party service.
- **High public-survey volume:** Turnstile (unlimited free) or hCaptcha (100k/mo free); watch reCAPTCHA's 10k/mo cap.
- **Already standardized on reCAPTCHA and v2's weakness is the only issue:** reCAPTCHA v3 here gives you score-based gating, but mind the cost cap and EU consent requirement.

### Common requirements (all providers)

- **REDCap** 13.7.3+ (External Module Framework v14).
- **PHP** 7.4+ with the **cURL** extension (used for server-side token verification).
- **Outbound HTTPS** from the REDCap server to the provider's verify endpoint —
  all four providers require this. A server behind a tight firewall may block it
  (this is the original reason local-only modules like nedCAPTCHA exist; if you
  need a no-outbound-dependency option, that or a self-hosted CAPTCHA is the way).
- The **participant's browser** must be able to load the provider's JavaScript.
  These providers are blocked in some networks (notably mainland China for Google
  reCAPTCHA).

## Getting keys for each provider

The same links appear in the module's configuration screen, next to each
provider's key fields.

| Provider | Get keys / sign up | Docs |
|---|---|---|
| Cloudflare Turnstile | [Cloudflare dashboard → Turnstile](https://dash.cloudflare.com/?to=/:account/turnstile) | [Setup docs](https://developers.cloudflare.com/turnstile/get-started/) |
| Google reCAPTCHA v3 | [reCAPTCHA admin console](https://www.google.com/recaptcha/admin/create) (choose **v3**) | [v3 docs](https://developers.google.com/recaptcha/docs/v3) |
| hCaptcha | [hCaptcha dashboard](https://dashboard.hcaptcha.com/signup) | [Developer docs](https://docs.hcaptcha.com/) |
| Friendly Captcha | [Create a sitekey](https://developer.friendlycaptcha.com/docs/v2/getting-started/setup) | [Developer hub](https://developer.friendlycaptcha.com/) |

Turnstile, reCAPTCHA, and hCaptcha all publish always-pass / always-fail **test
keys** you can use to exercise both paths without a real account.

### Using one keypair across multiple servers

Each provider binds a sitekey to a specific list of **allowed domains / hostnames**.
If you want to reuse the *same* sitekey + secret on more than one REDCap server
(e.g. a personal dev box and an institutional test server), you must **add every
server's hostname to that sitekey's allowed-domains list** in the provider's
dashboard. A sitekey will silently fail verification on any hostname that isn't
registered, so a keypair that works on one server will appear broken on another
until its domain is added. (Alternatively, create a separate sitekey per server.)
This applies to all four providers — Turnstile, reCAPTCHA v3, hCaptcha, and
Friendly Captcha.

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

## Known incompatibilities (other survey-gate modules)

This module takes over the public survey's first page via `redcap_survey_page_top`
+ `exitAfterHook()`. **Any other module that gates the same survey page the same
way will conflict**: the framework runs same-hook modules in sequence and the
first one to call `exitAfterHook()` ends the request, so the other never runs on
that page load. Module execution order is effectively the install order and is
**not** adjustable in the Control Center UI, so you can't reliably guarantee which
gate appears first.

The practical consequence: if a gate/auth module runs before Multi-CAPTCHA, it
renders first; once it establishes a record/auth context, Multi-CAPTCHA's
"no record yet" guard makes it skip — so **the CAPTCHA may never display**. Always
test the combined public-survey flow.

Modules known to gate the survey page (from the REDCap External Module repo):

| Module | Author | Notes |
|---|---|---|
| **REDCap PRO** (`redcappro`) | Andrew Poppe (Yale CCI) | Participant login/auth for Part 11. Renders a login on the survey page. In testing it runs before Multi-CAPTCHA, so the CAPTCHA is bypassed. |
| **REDCap Survey Auth** (`redcap_survey_auth`) | Günther Rezniczek | Login gate for surveys/public dashboards/reports. Only gates instruments carrying the `@SURVEY-AUTH` action tag, so the conflict is limited to those. |
| **nedCAPTCHA** (`nedcaptcha`) | Günther Rezniczek | Local (no-external-service) CAPTCHA gate for public surveys. Running it *and* Multi-CAPTCHA is redundant and conflicting — pick one. (Newer v2 behaves the same way.) |
| Other reCAPTCHA modules (e.g. Stanford `redcaptcha`) | various | Survey CAPTCHA gates. Note: basic reCAPTCHA v2 is also built into REDCap core. |

**Guidance for stacking with an auth module (e.g. REDCap PRO):**

- They coexist fine *system-wide* — the issue only arises when **both are enabled
  on the same project and the same public survey**. Prefer using Multi-CAPTCHA on
  open public surveys and the auth module where authentication is required.
- Do **not** enable Multi-CAPTCHA's *"always require the check"* when stacked — the
  auth module's reloads/redirects would re-trigger the CAPTCHA repeatedly.
- If you truly need a bot check *in front of* an auth login, that belongs inside
  the auth module (a CAPTCHA-on-login feature), not as a competing gate.

When Multi-CAPTCHA detects one of these enabled on the same project, it shows a
warning in its project configuration dialog (it only warns — it does not change
behavior). The detected set is a curated list of survey-auth modules plus any
other module whose prefix contains `captcha`.

## File layout

```
redcap_multicaptcha/                  (install as multicaptcha_v<version>)
├── config.json                       System + project settings (provider dropdowns, keys)
├── MultiCaptchaExternalModule.php    The gate logic (provider-agnostic)
├── ui.html                           Interstitial challenge page template
└── classes/
    ├── CaptchaProvider.php           Abstract base (4 methods + shared cURL helper)
    ├── ProviderFactory.php           id -> provider class
    ├── TurnstileProvider.php
    ├── RecaptchaV3Provider.php
    ├── HcaptchaProvider.php
    └── FriendlyCaptchaProvider.php
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

- **Friendly Captcha** uses the **v2** API (`global.frcapi.com`, API key in an
  `X-API-Key` header). For EU data residency, change the endpoint host to
  `eu.frcapi.com` in `FriendlyCaptchaProvider::verify()`. Legacy v1 sitekeys are
  not supported.
- The 8-second submit-enable safety net in `ui.html` prevents lockout if a
  provider callback never fires; server-side verification still gates entry.
- Consider rate-limiting / logging failed attempts if abuse is a concern.

## Security scanning

The module passes the Consortium's `bin/scan` (phpcs + Psalm taint analysis) with
**one expected false positive**:

> `TaintedSSRF` in `classes/CaptchaProvider.php` — user input (the visitor's
> CAPTCHA token from `$_POST`) flows into a cURL request body.

This is **not** an SSRF. SSRF requires an attacker-influenced request *URL*; here
the URL is a hardcoded provider verification endpoint
(`challenges.cloudflare.com`, `www.google.com/recaptcha`, `api.hcaptcha.com`,
`global.frcapi.com`). Sending the visitor's token to that fixed endpoint in the
POST body is exactly the intended verification step, and no PHI is transmitted
(the token plus, optionally, the visitor IP). The relevant lines carry a
`@psalm-suppress TaintedSSRF` annotation documenting this; note the scan does not
honor inline suppressions for taint findings, so it remains in the report and is
declared here as a reviewed false positive per the scan's own guidance
(`redcap-external-module-framework@vumc.org`).

## Credit

Gate strategy modeled on **nedCAPTCHA** by Günther A. Rezniczek
(<https://github.com/grezniczek/redcap_nedcaptcha>), MIT-licensed.
