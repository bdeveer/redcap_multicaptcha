<?php namespace YALE\MultiCaptchaExternalModule;

require_once "CaptchaProvider.php";

/**
 * Friendly Captcha. Proof-of-work, no interaction, GDPR/EU-oriented.
 * This implementation targets the v1 widget + siteverify endpoint.
 * Docs: https://developer.friendlycaptcha.com/
 *
 * NOTE: Friendly Captcha v2 uses a different endpoint (global.frcapi.com) and
 * an "X-API-Key" header. If you provision a v2 site, adjust verify() accordingly.
 */
class FriendlyCaptchaProvider extends CaptchaProvider
{
    public function getId() { return "friendly"; }
    public function getLabel() { return "Friendly Captcha"; }

    public function getScriptHtml()
    {
        return '<script type="module" '
             . 'src="https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.18/widget.module.min.js" async defer></script>'
             . '<script nomodule '
             . 'src="https://cdn.jsdelivr.net/npm/friendly-challenge@0.9.18/widget.min.js" async defer></script>';
    }

    public function getWidgetHtml()
    {
        // Injects a hidden input named "frc-captcha-solution".
        return '<div class="frc-captcha" data-sitekey="' . $this->attr($this->cfg["sitekey"]) . '" '
             . 'data-callback="mc_enableSubmit"></div>';
    }

    public function getResponseField() { return "frc-captcha-solution"; }

    public function verify($token, $remoteIp = null)
    {
        $r = $this->httpPost("https://api.friendlycaptcha.com/api/v1/siteverify", array(
            "solution" => $token,
            "secret"   => $this->cfg["secret"],
            "sitekey"  => $this->cfg["sitekey"],
        ));
        if (isset($r["_transport_error"])) {
            return array("success" => false, "error" => $r["_transport_error"], "score" => null);
        }
        return array(
            "success" => !empty($r["success"]),
            "error"   => !empty($r["success"]) ? null : implode(", ", (array)($r["errors"] ?? array("verification failed"))),
            "score"   => null,
        );
    }
}
