<?php namespace YALE\MultiCaptchaExternalModule;

require_once "CaptchaProvider.php";
require_once "TurnstileProvider.php";
require_once "RecaptchaV3Provider.php";
require_once "HcaptchaProvider.php";
require_once "FriendlyCaptchaProvider.php";
require_once "McaptchaProvider.php";

/**
 * Turns a provider id + resolved settings into a provider instance.
 * Adding a new bot-check service = add one class + one line here.
 */
class ProviderFactory
{
    public static function create($id, $cfg)
    {
        switch ($id) {
            case "turnstile":    return new TurnstileProvider($cfg);
            case "recaptcha_v3": return new RecaptchaV3Provider($cfg);
            case "hcaptcha":     return new HcaptchaProvider($cfg);
            case "friendly":     return new FriendlyCaptchaProvider($cfg);
            case "mcaptcha":     return new McaptchaProvider($cfg);
            default:             return null; // "none" or unknown
        }
    }
}
