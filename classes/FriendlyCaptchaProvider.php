<?php namespace YALE\MultiCaptchaExternalModule;

require_once "CaptchaProvider.php";

/**
 * Friendly Captcha (v2). Proof-of-work, no interaction, GDPR/EU-oriented.
 * Docs: https://developer.friendlycaptcha.com/
 *
 * v2 vs v1: the widget element (.frc-captcha) and the hidden form field
 * (frc-captcha-solution) are unchanged, but the client script and the
 * server-side verification differ:
 *   - script: @friendlycaptcha/sdk "site" build (auto-binds .frc-captcha)
 *   - verify: POST JSON to global.frcapi.com/api/v2/captcha/siteverify
 *             with the API key in an X-API-Key header (not the body)
 *
 * EU data residency: swap the endpoint for https://eu.frcapi.com/... if needed.
 */
class FriendlyCaptchaProvider extends CaptchaProvider
{
    public function getId() { return "friendly"; }
    public function getLabel() { return "Friendly Captcha"; }

    public function getScriptHtml()
    {
        // v2 auto-widget build: binds to any .frc-captcha element on the page.
        return '<script type="module" '
             . 'src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0/site.min.js" async defer></script>';
    }

    public function getWidgetHtml()
    {
        // data-start="auto" solves the proof-of-work on load. data-form-field-name
        // pins the hidden field name so the POSTed token matches getResponseField()
        // regardless of the v2 default. The "frc:widget.complete" event enables the
        // submit button (the ui.html 8s fallback also does, so nobody gets stuck).
        return '<div class="frc-captcha" data-sitekey="' . $this->attr($this->cfg["sitekey"]) . '" '
             . 'data-start="auto" data-form-field-name="' . $this->getResponseField() . '"></div>'
             . '<script>document.addEventListener("frc:widget.complete", function(){'
             . 'if(typeof mc_enableSubmit==="function")mc_enableSubmit();});</script>';
    }

    public function getResponseField() { return "frc-captcha-solution"; }

    public function verify($token, $remoteIp = null)
    {
        $r = $this->httpPost(
            "https://global.frcapi.com/api/v2/captcha/siteverify",
            array("response" => $token, "sitekey" => $this->cfg["sitekey"]),
            true,
            array("X-API-Key: " . $this->cfg["secret"])
        );
        if (isset($r["_transport_error"])) {
            return array("success" => false, "error" => $r["_transport_error"], "score" => null);
        }
        $ok = !empty($r["success"]);
        $err = null;
        if (!$ok) {
            // v2 returns a single error object: { error_code, detail }.
            $err = isset($r["error"]["error_code"]) ? $r["error"]["error_code"]
                 : (isset($r["error"]["detail"]) ? $r["error"]["detail"] : "verification failed");
        }
        return array("success" => $ok, "error" => $err, "score" => null);
    }
}
