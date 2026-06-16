<?php namespace YALE\MultiCaptchaExternalModule;

require_once "CaptchaProvider.php";

/**
 * Google reCAPTCHA v3. Invisible, score-based (no user interaction).
 * Returns a score 0.0–1.0; the module compares it to a configurable threshold.
 * Docs: https://developers.google.com/recaptcha/docs/v3
 */
class RecaptchaV3Provider extends CaptchaProvider
{
    public function getId() { return "recaptcha_v3"; }
    public function getLabel() { return "Google reCAPTCHA v3"; }

    public function getScriptHtml()
    {
        return '<script src="https://www.google.com/recaptcha/api.js?render='
             . $this->attr($this->cfg["sitekey"]) . '"></script>';
    }

    public function getWidgetHtml()
    {
        // v3 has no visible widget. Run execute() on load, drop the token into a
        // hidden field, and enable the submit button.
        $sitekey = $this->attr($this->cfg["sitekey"]);
        return '<input type="hidden" name="g-recaptcha-response" id="mc_recaptcha_token">'
             . '<script>'
             . 'function mc_runRecaptcha(){grecaptcha.ready(function(){'
             . 'grecaptcha.execute("' . $sitekey . '",{action:"survey"}).then(function(t){'
             . 'document.getElementById("mc_recaptcha_token").value=t;mc_enableSubmit();});});}'
             . 'mc_runRecaptcha();'
             . '</script>';
    }

    public function getResponseField() { return "g-recaptcha-response"; }

    public function verify($token, $remoteIp = null)
    {
        $r = $this->httpPost("https://www.google.com/recaptcha/api/siteverify", array(
            "secret"   => $this->cfg["secret"],
            "response" => $token,
            "remoteip" => $remoteIp,
        ));
        if (isset($r["_transport_error"])) {
            return array("success" => false, "error" => $r["_transport_error"], "score" => null);
        }
        $threshold = is_numeric($this->cfg["threshold"] ?? null) ? (float)$this->cfg["threshold"] : 0.5;
        $score = isset($r["score"]) ? (float)$r["score"] : null;
        $ok = !empty($r["success"]) && $score !== null && $score >= $threshold;
        return array(
            "success" => $ok,
            "error"   => $ok ? null : ("score " . ($score ?? "n/a") . " < threshold " . $threshold),
            "score"   => $score,
        );
    }
}
