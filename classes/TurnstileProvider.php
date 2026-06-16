<?php namespace YALE\MultiCaptchaExternalModule;

require_once "CaptchaProvider.php";

/**
 * Cloudflare Turnstile. Free, no interaction, privacy-friendly.
 * Docs: https://developers.cloudflare.com/turnstile/
 */
class TurnstileProvider extends CaptchaProvider
{
    public function getId() { return "turnstile"; }
    public function getLabel() { return "Cloudflare Turnstile"; }

    public function getScriptHtml()
    {
        return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }

    public function getWidgetHtml()
    {
        // The widget auto-injects a hidden input named "cf-turnstile-response".
        // data-callback enables the submit button once a token is issued.
        return '<div class="cf-turnstile" data-sitekey="' . $this->attr($this->cfg["sitekey"]) . '" '
             . 'data-callback="mc_enableSubmit"></div>';
    }

    public function getResponseField() { return "cf-turnstile-response"; }

    public function verify($token, $remoteIp = null)
    {
        $r = $this->httpPost("https://challenges.cloudflare.com/turnstile/v0/siteverify", array(
            "secret"   => $this->cfg["secret"],
            "response" => $token,
            "remoteip" => $remoteIp,
        ));
        if (isset($r["_transport_error"])) {
            return array("success" => false, "error" => $r["_transport_error"], "score" => null);
        }
        return array(
            "success" => !empty($r["success"]),
            "error"   => !empty($r["success"]) ? null : implode(", ", (array)($r["error-codes"] ?? array("verification failed"))),
            "score"   => null,
        );
    }
}
