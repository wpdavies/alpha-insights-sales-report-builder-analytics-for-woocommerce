<?php
/**
 * Class WPDAI_User_Agent_Classification
 *
 * Analyzes and classifies user agent strings for Alpha Insights.
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 */

defined('ABSPATH') || exit;

class WPDAI_User_Agent_Classification {
    /**
     * @var string Device category (e.g., Mobile, Tablet, Console, Television, Desktop, Unknown)
     */
    protected $device_category = 'Unknown';

    /**
     * @var string Raw user agent string (lowercased)
     */
    protected $agent;

    /**
     * @var string Operating system name
     */
    protected $os = 'Unknown';

    /**
     * @var string Browser name
     */
    protected $browser = 'Unknown';

    /**
     * @var string|null Browser prefix used for version detection
     */
    protected $prefix;

    /**
     * @var string|null Browser version
     */
    protected $version;

    /**
     * @var string|null Rendering engine
     */
    protected $engine;

    /**
     * @var string Device name
     */
    protected $device = 'Computer';

    /**
     * @var int 1 if bot/crawler detected, 0 otherwise
     */
    protected $isBot = 0;

    /**
     * @var array List of OS patterns
     */
    protected $oss = [
        'Android' => ['Android'],
        'Linux' => ['linux', 'Linux'],
        'Mac OS X' => ['Macintosh', 'Mac OS X'],
        'iOS' => ['like Mac OS X'],
        'Windows' => ['Windows NT', 'win32'],
        'Windows Phone' => ['Windows Phone'],
        'Chrome OS' => ['CrOS'],
    ];

    /**
     * @var array List of browser patterns (ORDERED - most specific first)
     * Note: Edge and Opera must come before Chrome since they include "Chrome" in their UA
     * Note: Chrome must come before Safari since Chrome includes "Safari" in its UA
     */
    protected $browsers = [
        'Edge' => ['Edg/'],  // Check Edg/ before Chrome (Edge uses Chrome in UA)
        'Opera' => ['OPR/'],  // Check OPR/ before Chrome (Opera uses Chrome in UA)
        'Mozilla Firefox' => ['Firefox'],
        'Google Chrome' => ['Chrome'],  // Check Chrome before Safari (Chrome includes Safari in UA)
        'Apple Safari' => ['Safari'],  // Check Safari last since it appears in Chrome/Edge/Opera UAs
        'Internet Explorer' => ['MSIE'],
        'Netscape' => ['Netscape'],
        'cURL' => ['curl'],
        'Wget' => ['Wget'],
    ];

    /**
     * @var array List of rendering engine patterns
     */
    protected $engines = [
        'Blink' => ['AppleWebKit'],
        'WebKit' => ['X) AppleWebKit'],
        'Gecko' => ['Gecko'],
        'EdgeHTML' => ['Edge'],
        'Trident' => ['Trident', 'MSIE'],
    ];

    /**
     * @var array List of device patterns
     */
    protected $devices = [
        'iPad' => ['iPad'],
        'iPhone' => ['iPhone'],
        'Samsung' => ['SAMSUNG', 'SM-G'],
        'HTC' => ['HTC'],
        'Sony Xperia' => ['G8231', 'E6653'],
        'Amazon Kindle' => ['Kindle'],
        'Nintendo 3DS' => ['Nintendo 3DS'],
        'Nintendo Wii U' => ['Nintendo WiiU'],
        'Playstation Vita' => ['Playstation Vita'],
        'Playstation 4' => ['Playstation 4'],
        'Xbox One' => ['Xbox One'],
        'Xbox One S' => ['XBOX_ONE_ED'],
        'Apple TV' => ['AppleTV'],
        'Google Nexus Player' => ['Nexus Player'],
        'Amazon Fire TV' => ['AFTS'],
        'Chromecast' => ['CrKey'],
    ];

    /**
     * @var array|null Cached crawler/bot patterns
     */
    protected $crawlers_bots = null;

    /**
     * Constructor.
     *
     * @param string|null $agent Optional user agent string. Defaults to $_SERVER['HTTP_USER_AGENT'] if not provided.
     */
    public function __construct($agent = null)
    {
        if (!$agent && isset($_SERVER['HTTP_USER_AGENT'])) {
            $agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
        }
        $this->agent = $agent;
        $this->parse();
    }

    /**
     * Parse the user agent string and populate properties.
     *
     * @return void
     */
    public function parse()
    {
        if (!empty($this->agent)) {
            // Detect OS
            foreach ($this->oss as $os => $patterns) {
                foreach ($patterns as $pattern) {
                    if (stripos($this->agent, $pattern) !== false) {
                        $this->os = $os;
                        break 2;
                    }
                }
            }
            // Detect browser (check most specific first - Edge/Opera before Chrome, Chrome before Safari)
            foreach ($this->browsers as $browser => $patterns) {
                foreach ($patterns as $pattern) {
                    // Remove trailing slash for prefix storage, but check with slash for accuracy
                    $pattern_clean = rtrim($pattern, '/');
                    if (stripos($this->agent, $pattern) !== false) {
                        // Special case: Safari appears in Chrome/Edge/Opera UAs, so only match Safari if Chrome/Edge/Opera not present
                        if ($browser === 'Apple Safari') {
                            // Don't match Safari if Chrome, Edge, or Opera patterns are present
                            if (stripos($this->agent, 'Chrome') !== false || 
                                stripos($this->agent, 'Edg/') !== false || 
                                stripos($this->agent, 'OPR/') !== false) {
                                continue; // Skip Safari match, continue to next browser
                            }
                        }
                        $this->browser = $browser;
                        $this->prefix = $pattern_clean;
                        break 2;
                    }
                }
            }
            // Detect engine
            foreach ($this->engines as $engine => $patterns) {
                foreach ($patterns as $pattern) {
                    if (stripos($this->agent, $pattern) !== false) {
                        $this->engine = $engine;
                        break 2;
                    }
                }
            }
            // Detect device
            foreach ($this->devices as $device => $patterns) {
                foreach ($patterns as $pattern) {
                    if (stripos($this->agent, $pattern) !== false) {
                        $this->device = $device;
                        break 2;
                    }
                }
            }

            // Device category - check for "Mobile" in UA first (catches "Mobile Safari")
            if (preg_match('/\bMobile\b/i', $this->agent)) {
                $this->device_category = 'Mobile';
                if ($this->device === 'Computer') {
                    $this->device = 'Mobile';
                }
            } elseif (in_array($this->device, ['Android', 'iPhone', 'Samsung', 'HTC', 'Sony Xperia'])) {
                $this->device_category = 'Mobile';
            } elseif (in_array($this->device, ['iPad', 'Amazon Kindle'])) {
                $this->device_category = 'Tablet';
            } elseif (in_array($this->device, ['Nintendo 3DS', 'Nintendo Wii U', 'Playstation Vita', 'Playstation 4', 'Xbox One', 'Xbox One S'])) {
                $this->device_category = 'Console';
            } elseif (in_array($this->device, ['Apple TV', 'Google Nexus Player', 'Amazon Fire TV', 'Chromecast'])) {
                $this->device_category = 'Television';
            } else {
                $this->device_category = 'Desktop';
            }

            // Browser version - handle different formats
            if ($this->browser === 'Edge' && preg_match('/Edg\/([0-9.]+)/i', $this->agent, $m)) {
                // Edge uses Edg/version format
                $this->version = $m[1];
            } elseif ($this->browser === 'Opera' && preg_match('/OPR\/([0-9.]+)/i', $this->agent, $m)) {
                // Opera uses OPR/version format
                $this->version = $m[1];
            } elseif ($this->prefix) {
                // Standard version extraction for other browsers
                $pattern = '#(?<browser>' . join('|', ['Version', $this->prefix, 'other']) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
                preg_match_all($pattern, $this->agent, $matches);
                if (isset($matches['version'][0])) {
                    $this->version = $matches['version'][0];
                }
                if (
                    isset($matches['browser']) && is_array($matches['browser']) &&
                    isset($matches['version']) && is_array($matches['version']) &&
                    count($matches['browser']) > 1 && !is_null($this->prefix)
                ) {
                    // Defensive: check that index 1 exists
                    if (isset($matches['version'][1]) && isset($matches['version'][0])) {
                        $this->version = strripos($this->agent, "Version") < strripos($this->agent, $this->prefix)
                            ? $matches['version'][0]
                            : $matches['version'][1];
                    } elseif (isset($matches['version'][0])) {
                        $this->version = $matches['version'][0];
                    } else {
                        $this->version = null;
                    }
                }
            }
        }

        // Bot checks
        if (empty($this->agent)) {
            $this->isBot = 1;
            $this->device = 'BOT';
            $this->device_category = 'BOT';
        } elseif ($this->crawlerBotCheck($this->agent)) {
            $this->isBot = 1;
            $this->device = 'BOT';
            $this->device_category = 'BOT';
        }
    }

    /**
     * Check if the user agent is a crawler or bot.
     *
     * @param string $agent
     * @return bool
     */
    public function crawlerBotCheck($agent)
    {
        $agent = strtolower($agent);
        if ($this->isGenericBot($agent)) {
            $this->isBot = 1;
            return true;
        }
        if ($this->isSpecificBot($agent)) {
            $this->isBot = 1;
            return true;
        }
        $this->isBot = 0;
        return false;
    }

    /**
     * Check for generic bot/crawler keywords in the agent string.
     *
     * @param string $agent
     * @return int 1 if match, 0 otherwise
     */
    protected function isGenericBot($agent)
    {
        // Use word boundaries to avoid partial matches
        // Also check for pattern like "AhrefsBot/7.0" or "SomeBot/1.0"
        return preg_match('/\\b(bot|crawl|archiver|transcoder|spider|uptime|validator|fetcher|cron|checker|reader|extractor|monitoring|analyzer|scraper|storebot)\\b/i', $agent)
            || preg_match('/[a-z]+Bot\/[0-9]/i', $agent); // Pattern like "AhrefsBot/7.0"
    }

    /**
     * Check for specific bot/crawler patterns in the agent string.
     *
     * @param string $agent
     * @return int 1 if match, 0 otherwise
     */
    protected function isSpecificBot($agent)
    {
        $escaped_patterns = array_map(function($pattern) {
            return preg_quote($pattern, '/');
        }, $this->getCrawlerBots());
        $compiled_regex = '(' . implode('|', $escaped_patterns) . ')';
        return preg_match("/{$compiled_regex}/i", $agent);
    }

    /**
     * Set a new user agent string and re-parse.
     *
     * @param string $agent
     * @return void
     */
    public function setAgent($agent)
    {
        $this->agent = $agent;
        $this->parse();
    }

    /**
     * Returns whether the current agent is a bot/crawler.
     *
     * @return int 1 if bot, 0 otherwise
     */
    public function isBot()
    {
        return $this->isBot;
    }

    /**
     * Get all parsed user agent info as an array.
     *
     * @return array
     */
    public function getInfo()
    {
        return [
            'agent' => $this->getAgent(),
            'device' => $this->getDevice(),
            'device_category' => $this->getDeviceCategory(),
            'os' => $this->getOS(),
            'browser' => $this->getBrowser(),
            'engine' => $this->getEngine(),
            'prefix' => $this->getPrefix(),
            'version' => $this->getVersion(),
            'is_bot' => $this->isBot() ? 'true' : 'false',
        ];
    }

    /**
     * Get the device category (Mobile, Tablet, Console, etc.).
     *
     * @return string
     */
    public function getDeviceCategory()
    {
        return $this->device_category;
    }

    /**
     * Get the detected device name.
     *
     * @return string
     */
    public function getDevice()
    {
        return $this->device;
    }

    /**
     * Get the detected rendering engine.
     *
     * @return string|null
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * Get the raw user agent string (lowercased).
     *
     * @return string
     */
    public function getAgent()
    {
        return $this->agent;
    }

    /**
     * Get the detected operating system.
     *
     * @return string
     */
    public function getOS()
    {
        return $this->os;
    }

    /**
     * Get the detected browser name.
     *
     * @return string
     */
    public function getBrowser()
    {
        return $this->browser;
    }

    /**
     * Get the browser prefix used for version detection.
     *
     * @return string|null
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Get the detected browser version.
     *
     * @return string|null
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Get the user agent string (alias for getAgent).
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->agent;
    }

    /**
     * Lazy-load the crawler bot patterns from the data file.
     *
     * @return array
     */
    protected function getCrawlerBots()
    {
        if ($this->crawlers_bots === null) {
            $this->crawlers_bots = require(WPD_AI_PATH . 'includes/classes/WPDAI_Crawler_Bots.php');
        }
        return $this->crawlers_bots;
    }
}