<?php namespace YALE\MultiCaptchaExternalModule;

/**
 * Base class for all bot-check providers.
 *
 * Every provider only has to answer four questions:
 *   1. What <script> tag(s) does the page need?            -> getScriptHtml()
 *   2. What widget markup goes inside the form?            -> getWidgetHtml()
 *   3. What POST field carries the token on submit?        -> getResponseField()
 *   4. Given that token, did the visitor pass?             -> verify()
 *
 * Everything else (the survey gate, the session handling, the redirect) lives
 * in the main module and is identical for all providers.
 */
abstract class CaptchaProvider
{
    /** @var array Associative array of resolved settings (sitekey, secret, etc.). */
    protected $cfg;

    public function __construct($cfg)
    {
        $this->cfg = $cfg;
    }

    /** Short identifier, e.g. "turnstile". */
    abstract public function getId();

    /** Human-readable label, e.g. "Cloudflare Turnstile". */
    abstract public function getLabel();

    /** HTML <script> tag(s) to load the provider's client library. */
    abstract public function getScriptHtml();

    /**
     * Widget markup placed inside the gate form. Most providers inject their
     * own hidden token field; reCAPTCHA v3 gets a little glue JS here.
     */
    abstract public function getWidgetHtml();

    /** Name of the POST field that carries the token on submit. */
    abstract public function getResponseField();

    /**
     * Server-side verification.
     * @return array { success: bool, error: ?string, score: ?float }
     */
    abstract public function verify($token, $remoteIp = null);

    /** True only if the keys this provider needs are present. */
    public function isConfigured()
    {
        return !empty($this->cfg["sitekey"]) && !empty($this->cfg["secret"]);
    }

    /**
     * Small cURL helper shared by all providers.
     * $body may be an array (sent as application/x-www-form-urlencoded) or a
     * JSON string (when $json = true). $headers is an optional list of extra
     * HTTP header strings (e.g. "X-API-Key: ...").
     * @return array|null Decoded JSON response, or null on transport failure.
     */
    protected function httpPost($url, $body, $json = false, $headers = array())
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $hdrs = is_array($headers) ? $headers : array();
        if ($json) {
            $hdrs[] = "Content-Type: application/json";
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
        }
        if (!empty($hdrs)) curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $raw === "") {
            return array("_transport_error" => $err ?: "Empty response from $url");
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array("_transport_error" => "Non-JSON response from $url");
    }

    /** Convenience: escape a value for safe placement in an HTML attribute. */
    protected function attr($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
    }
}
