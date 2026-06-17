# Changelog

All notable changes to this module are documented here.

## v0.1.0 — 2026-06-15

Initial release (scaffold).

- Gate public surveys with a pluggable bot-check provider.
- Supported providers: Cloudflare Turnstile, Google reCAPTCHA v3, hCaptcha,
  Friendly Captcha.
- Global default configuration with optional per-project override.
- "Always require" option to challenge on every survey load.
- Provider abstraction (`CaptchaProvider` + factory): adding a provider is one
  class plus one factory line.
- Gate-entry strategy (intercept `redcap_survey_page_top` + `exitAfterHook()`),
  modeled on Günther Rezniczek's nedCAPTCHA.
- Server-side token verification per provider; fails closed on error.
- Provider key/setup links shown directly in the configuration UI.
- Manual test checklist with published dummy test keys.
