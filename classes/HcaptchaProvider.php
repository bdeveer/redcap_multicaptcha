<?php namespace YALE\MultiCaptchaExternalModule;

require_once "CaptchaProvider.php";

/**
 * hCaptcha. Privacy-focused drop-in; checkbox or invisible modes.
 * Docs: https://docs.hcaptcha.com/
 */
class HcaptchaProvider extends CaptchaProvider
{
    public function getId() { return "hcaptcha"; }
    public function getLabel() { return "hCaptcha"; }

    public function getScriptHtml()
    {
        return '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
    }

    public function getWidgetHtml()
    {
        // Injects a hidden input named "h-captcha-response".
        return '<div class="h-captcha" data-sitekey="' . $this->attr($this->cfg["sitekey"]) . '" '
             . 'data-callback="mc_enableSubmit"></div>';
    }

    public function getResponseField() { return "h-captcha-response"; }

    public function verify($token, $remoteIp = null)
    {
        $r = $this->httpPost("https://api.hcaptcha.com/siteverify", array(
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
