# Changelog

All notable changes to this module are documented here.

## Unreleased

- Gate page follows the survey theme: the title carries `id="surveytitle"`, so
  REDCap's theme CSS gives it the theme's title text color. Before, the theme
  painted the page background but the title stayed dark (dark text on a dark
  title band).
- Continue button is now a light button (`btn-light`, like REDCap's own survey
  Submit), so a theme's button text color stays readable. On `btn-primary` it
  landed on Bootstrap blue.

## v1.0.0 — 2026-07-02

Initial public release.

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
- Built on External Module Framework v16 (REDCap 15.0.9+ LTS / 14.6.4+ standard).
