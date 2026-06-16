<?php namespace YALE\MultiCaptchaExternalModule;

use ExternalModules\AbstractExternalModule;

require_once "classes/ProviderFactory.php";

/**
 * Multi-CAPTCHA External Module.
 *
 * Gates the first page of a PUBLIC survey behind a bot-check provider chosen by
 * the admin (globally) or by the project (override). The gate strategy is the
 * same one used by Günther Rezniczek's nedCAPTCHA: instead of trying to reject a
 * survey *submission* (REDCap has no clean hook for that), we intercept
 * redcap_survey_page_top, render our own challenge page, and call
 * exitAfterHook() so REDCap never renders the survey until the visitor passes.
 *
 * Per-provider differences are isolated in the provider classes; everything in
 * this file is provider-agnostic.
 */
class MultiCaptchaExternalModule extends AbstractExternalModule
{
    const GET_NAME = "__mc_ok";

    /** @var CaptchaProvider|null */
    private $provider = null;
    private $debug = false;
    private $always = false;

    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance = 1)
    {
        // Only gate the very first page (no record yet).
        if (!empty($record)) return false;

        // Resolve which provider + keys apply to this project.
        if (!$this->resolveConfig()) return false;       // provider "none" or not configured
        $provider = $this->provider;

        // Only gate the PUBLIC survey link, never authenticated / regular survey pages.
        $survey_id = $GLOBALS["Proj"]->forms[$instrument]["survey_id"];
        $public_hash = \Survey::getSurveyHash($survey_id, $event_id);
        if ($public_hash !== $survey_hash) return false;

        $sessKey = $this->PREFIX . "-passed";

        // --- Handle the clean redirect that follows a successful check. ---
        if (isset($_GET[self::GET_NAME]) && $_GET[self::GET_NAME] === ($_SESSION[$this->PREFIX . "-check"] ?? null)) {
            unset($_SESSION[$this->PREFIX . "-check"]);
            // Strip our marker from the address bar, then let the survey render.
            print "<script>history.replaceState(null,'',window.location.href.replace(/([?&])" . self::GET_NAME . "=[^&]*(&|$)/,'$1').replace(/[?&]$/,''));</script>";
            return false;
        }

        // Already passed earlier this session? Let through unless "always" is set.
        if (!empty($_SESSION[$sessKey]) && !$this->always) return false;

        // --- Is this a postback from our gate form? ---
        $field = $provider->getResponseField();
        $isPostback = $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST[$field]);
        $failMsg = null;

        if ($isPostback) {
            $token = trim((string)$_POST[$field]);
            $remoteIp = trim(explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"] ?? ($_SERVER["REMOTE_ADDR"] ?? ""))[0]);
            $result = $provider->verify($token, $remoteIp ?: null);
            if ($this->debug) {
                print "<script>console.log('[Multi-CAPTCHA] verify (" . js_escape($provider->getId()) . "):', " . json_encode($result) . ");</script>";
            }
            if (!empty($result["success"])) {
                // PASS: flag the session and redirect to a clean GET so the survey renders normally.
                $guid = self::guid();
                $_SESSION[$sessKey] = true;
                $_SESSION[$this->PREFIX . "-check"] = $guid;
                $this->exitAfterHook();
                $url = APP_PATH_SURVEY_FULL . "?" . self::GET_NAME . "=" . $guid . "&" . $_SERVER["QUERY_STRING"];
                print "<script>window.location.href=" . json_encode($url) . ";</script>";
                return;
            }
            $failMsg = $result["error"] ?: "Verification failed. Please try again.";
        }

        // --- Render the challenge page in place of the survey. ---
        $this->renderGate($project_id, $provider, $failMsg);
        $this->exitAfterHook(); // suppress the real survey page
    }

    /**
     * Enforce the global override policy when project settings are saved.
     * Returns an error string to block the save, or null to allow it.
     */
    function validateSettings($settings)
    {
        // Only relevant when saving a project's settings.
        if (!$this->getProjectId()) return null;
        if (empty($settings["override"])) return null; // not enabling an override

        $policy = $this->getSystemSetting("override_policy");
        if ($policy === "disabled") {
            return "Project-level CAPTCHA overrides are disabled by your REDCap administrator.";
        }
        if ($policy === "admin" && !$this->currentUserIsSuperUser()) {
            return "Only REDCap administrators may configure a project-level CAPTCHA override.";
        }
        return null;
    }

    /** Best-effort super-user check for the current user. */
    private function currentUserIsSuperUser()
    {
        if (defined("SUPER_USER")) return (string)SUPER_USER === "1" || SUPER_USER === true;
        $user = method_exists($this, "getUser") ? $this->getUser() : null;
        return ($user && method_exists($user, "isSuperUser")) ? $user->isSuperUser() : false;
    }

    /**
     * Decide which provider + settings to use (project override or global default)
     * and instantiate the provider. Returns false if disabled / unconfigured.
     */
    private function resolveConfig()
    {
        // Project override is governed by a global policy: "disabled" forces the
        // global config; "user" and "admin" both allow a project to override (the
        // user/admin distinction only controls who may edit the project settings,
        // enforced in validateSettings()). An unset policy behaves as "user".
        $policy = $this->getSystemSetting("override_policy");
        $override = ($policy !== "disabled") && (bool)$this->getProjectSetting("override");
        $this->always = (bool)$this->getProjectSetting("always");
        $this->debug  = (bool)$this->getSystemSetting("debug") || (bool)$this->getProjectSetting("p_debug");

        if ($override) {
            $id  = $this->getProjectSetting("p_provider");
            $cfg = array(
                "sitekey"   => $this->getProjectSetting("p_{$id}_sitekey"),
                "secret"    => $this->getProjectSetting("p_{$id}_secret"),
                "threshold" => $this->getProjectSetting("p_recaptcha_threshold"),
                "instance"  => $this->getProjectSetting("p_mcaptcha_instance"),
            );
        } else {
            $id  = $this->getSystemSetting("provider");
            $cfg = array(
                "sitekey"   => $this->getSystemSetting("{$id}_sitekey"),
                "secret"    => $this->getSystemSetting("{$id}_secret"),
                "threshold" => $this->getSystemSetting("recaptcha_threshold"),
                "instance"  => $this->getSystemSetting("mcaptcha_instance"),
            );
        }

        if (empty($id) || $id === "none") return false;
        $provider = ProviderFactory::create($id, $cfg);
        if ($provider === null || !$provider->isConfigured()) return false;

        $this->provider = $provider;
        return true;
    }

    /** Print the interstitial challenge page from ui.html. */
    private function renderGate($project_id, $provider, $failMsg)
    {
        $template = file_get_contents(dirname(__FILE__) . "/ui.html");
        $action = APP_PATH_SURVEY_FULL . "?" . $_SERVER["QUERY_STRING"];

        // Survey logo, if the project has one (local file storage).
        $logo = "";
        if (isset($GLOBALS["logo"]) && is_numeric($GLOBALS["logo"])) {
            $logo = '<div style="padding:10px 0;"><img src="' . APP_PATH_SURVEY . "index.php?pid={$project_id}&doc_id_hash="
                  . \Files::docIdHash($GLOBALS["logo"]) . "&__passthru=" . urlencode("DataEntry/image_view.php")
                  . "&s={$GLOBALS["hash"]}&id={$GLOBALS["logo"]}" . '" style="max-width:600px;" alt=""></div>';
        }

        $replace = array(
            "{LOGO}"        => $logo,
            "{TITLE}"       => htmlspecialchars($GLOBALS["title"] ?? "", ENT_QUOTES, "UTF-8"),
            "{ACTION}"      => htmlspecialchars($action, ENT_QUOTES, "UTF-8"),
            "{SCRIPTS}"     => $provider->getScriptHtml(),
            "{WIDGET}"      => $provider->getWidgetHtml(),
            "{FAILMSG}"     => $failMsg ? htmlspecialchars($failMsg, ENT_QUOTES, "UTF-8") : "",
            "{FAILDISPLAY}" => $failMsg ? "block" : "none",
            "{PROVIDER}"    => htmlspecialchars($provider->getLabel(), ENT_QUOTES, "UTF-8"),
        );
        print str_replace(array_keys($replace), array_values($replace), $template);
    }

    private static function guid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }
}
