# Multi-CAPTCHA — Manual Test Checklist

Run these on a dev instance before each release. The module only acts on the
**public survey** link, so all tests use that link unless noted.

## Setup (per provider)

Get test keys here (links are also shown in the module config UI):

| Provider | Where to get keys |
|---|---|
| Cloudflare Turnstile | https://dash.cloudflare.com/?to=/:account/turnstile (docs: https://developers.cloudflare.com/turnstile/get-started/) |
| Google reCAPTCHA v3 | https://www.google.com/recaptcha/admin/create — choose **reCAPTCHA v3** (docs: https://developers.google.com/recaptcha/docs/v3) |
| hCaptcha | https://dashboard.hcaptcha.com/signup (docs: https://docs.hcaptcha.com/) |
| Friendly Captcha | https://developer.friendlycaptcha.com/docs/v2/getting-started/setup (hub: https://developer.friendlycaptcha.com/) |

### Dummy test keys (no account needed)

Use these published test keys to exercise the pass/fail paths. They work from any
domain, including localhost.

**Cloudflare Turnstile**

| Scenario | Site key | Secret key |
|---|---|---|
| Always passes | `1x00000000000000000000AA` | `1x0000000000000000000000000000000AA` |
| Always blocks / fails | `2x00000000000000000000AB` | `2x0000000000000000000000000000000AA` |
| Forces interactive challenge | `3x00000000000000000000FF` | (pair with a pass/fail secret above) |

Dummy site keys return a `XXXX.DUMMY.TOKEN.XXXX` token, which only the dummy
secrets will accept — don't mix dummy keys with a production secret.

**hCaptcha**

| Scenario | Site key | Secret key |
|---|---|---|
| Always passes (never challenges) | `10000000-ffff-ffff-ffff-000000000001` | `0x0000000000000000000000000000000000000000` |

For an always-**fail** check, tamper with the returned token before submit, or use
a real key with a mismatched secret.

**Google reCAPTCHA v3** — Google's public test keys are **v2 only**; there are no
always-pass v3 keys. Test v3 with your own low-traffic key, and verify the
threshold logic by temporarily setting the passing score very high (e.g. `0.9`) so
a normal visit is rejected, then back to `0.5`.

**Friendly Captcha** — no dummy keys; test with a real (free) sitekey.

Sources: [Cloudflare Turnstile testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/) ·
[hCaptcha docs](https://docs.hcaptcha.com/) ·
[reCAPTCHA FAQ](https://developers.google.com/recaptcha/docs/faq)

## Preconditions

- [ ] Module installed at `modules/multicaptcha_v0.1.0` and enabled in Control Center.
- [ ] A project with a survey that has a **public survey link** enabled.

## A. Global default path

- [ ] Set a global provider + valid keys; leave the project **Override** unchecked.
- [ ] Open the public survey link → the gate page appears instead of the survey.
- [ ] Complete the challenge → redirected to the survey; URL no longer shows `__mc_ok`.
- [ ] Advance/submit the survey → behaves normally (no re-prompt).
- [ ] Re-open the public link in the **same** session → not re-challenged.

## B. Per-project override

- [ ] Tick **Override global settings**, pick a *different* provider + keys.
- [ ] Public link now shows the project's provider (not the global one).
- [ ] Verification passes with valid keys.

## C. Failure handling

- [ ] Use a failing/dummy "always-fail" key (or tamper with the token) → gate
      re-renders with the failure message; survey is **not** reachable.
- [ ] Wrong/empty secret → fails closed (survey stays gated).
- [ ] Network to the provider blocked → fails closed with a transport error
      (check console in debug mode).

## D. "Always require" option

- [ ] Enable **Always require the check**.
- [ ] Pass the gate, then reload the public link → challenged again.

## E. Provider matrix

Repeat A (pass) and C (fail) for each provider you intend to support:

- [ ] Cloudflare Turnstile
- [ ] Google reCAPTCHA v3 (also: set threshold high → low score is rejected)
- [ ] hCaptcha
- [ ] Friendly Captcha

## F. Non-targets (should NOT be gated)

- [ ] Authenticated data-entry pages — no gate.
- [ ] A non-public survey link — no gate.
- [ ] Second+ pages of a survey / existing records — no gate.

## G. Edge

- [ ] Provider = **None** → survey loads with no gate.
- [ ] Survey logo renders on the gate page if the project has one.
- [ ] Debug mode prints verify results to the browser console.
