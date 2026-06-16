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
| Friendly Captcha | https://developer.friendlycaptcha.com/docs/getting-started/client (hub: https://developer.friendlycaptcha.com/) |
| mCaptcha | self-hosted; create a sitekey on your instance (docs: https://mcaptcha.org/docs/) |

> Turnstile, reCAPTCHA, and hCaptcha all publish **test/dummy keys** that always
> pass or always fail — use those to exercise the pass/fail paths without a real account.

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
- [ ] mCaptcha (confirm widget markup + `siteverify` payload against your instance version)

## F. Non-targets (should NOT be gated)

- [ ] Authenticated data-entry pages — no gate.
- [ ] A non-public survey link — no gate.
- [ ] Second+ pages of a survey / existing records — no gate.

## G. Edge

- [ ] Provider = **None** → survey loads with no gate.
- [ ] Survey logo renders on the gate page if the project has one.
- [ ] Debug mode prints verify results to the browser console.
