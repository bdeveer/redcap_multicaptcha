<?php namespace YALE\MultiCaptchaExternalModule;

require_once "CaptchaProvider.php";

/**
 * mCaptcha. Open-source, self-hostable, proof-of-work — no third party at all.
 * Requires an instance base URL in addition to a site key + account secret.
 * Docs: https://mcaptcha.org/docs/
 *
 * NOTE: mCaptcha's widget/glue and API have evolved across versions. The markup
 * and the verify payload below match the common self-hosted layout; confirm them
 * against the version you actually deploy before going live.
 */
class McaptchaProvider extends CaptchaProvider
{
    public function getId() { return "mcaptcha"; }
    public function getLabel() { return "mCaptcha"; }

    private function instance()
    {
        return rtrim((string)($this->cfg["instance"] ?? ""), "/");
    }

    public function isConfigured()
    {
        return parent::isConfigured() && $this->instance() !== "";
    }

    public function getScriptHtml()
    {
        // Glue script served by the mCaptcha instance.
        return '<script src="' . $this->attr($this->instance()) . '/widget/index.js" async defer></script>';
    }

    public function getWidgetHtml()
    {
        // The widget is an iframe pointing at the instance; it injects a hidden
        // input named "mcaptcha__token" on success.
        $src = $this->attr($this->instance() . "/widget/?sitekey=" . urlencode($this->cfg["sitekey"]));
        return '<div class="m-captcha">'
             . '<iframe src="' . $src . '" role="presentation" '
             . 'style="width:304px;height:78px;border:0;" '
             . 'onload="mc_enableSubmit()"></iframe>'
             . '</div>';
    }

    public function getResponseField() { return "mcaptcha__token"; }

    public function verify($token, $remoteIp = null)
    {
        $r = $this->httpPost($this->instance() . "/api/v1/pow/siteverify", array(
            "token"  => $token,
            "key"    => $this->cfg["sitekey"],
            "secret" => $this->cfg["secret"],
        ), true);
        if (isset($r["_transport_error"])) {
            return array("success" => false, "error" => $r["_transport_error"], "score" => null);
        }
        return array(
            "success" => !empty($r["valid"]),
            "error"   => !empty($r["valid"]) ? null : "mCaptcha token invalid",
            "score"   => null,
        );
    }
}
