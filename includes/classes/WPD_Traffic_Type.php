<?php
/**
 *
 * Class that analyses traffic type by referral source
 *
 * @package Alpha Insights
 * @version 1.0.0
 * @author WPDavies
 * @link https://wpdavies.dev/
 *
 */
defined( 'ABSPATH' ) || exit;


/**
 *
 *  Class to determine traffic type
 *
 */
class WPD_Traffic_Type {

    /*
     * Organic sources
     */
    protected $organic_sources = array(

        'www.google'            => array('q='),
        'bing.com/'             => array('bing.com/'),
        'daum.net/'             => array('q='),
        'eniro.se/'             => array('search_word=', 'hitta:'),
        'naver.com/'            => array('query='),
        'yahoo.com/'            => array('p='),
        'msn.com/'              => array('q='),
        'bing.com/'             => array('q='),
        'aol.com/'              => array('query=', 'encquery='),
        'lycos.com/'            => array('query='),
        'ask.com/'              => array('q='),
        'altavista.com/'        => array('q='),
        'search.netscape.com/'  => array('query='),
        'cnn.com/SEARCH/'       => array('query='),
        'about.com/'            => array('terms='),
        'mamma.com/'            => array('query='),
        'alltheweb.com/'        => array('q='),
        'voila.fr/'             => array('rdata='),
        'search.virgilio.it/'   => array('qs='),
        'baidu.com/'            => array('wd='),
        'alice.com/'            => array('qs='),
        'yandex.com'            => array('text='),
        'yandex.ru'             => array('text='),
        'ya.ru'                 => array('text='),
        'najdi.org.mk/'         => array('q='),
        'aol.com/'              => array('q='),
        'seznam.cz/'            => array('q='),
        'search.com/'           => array('q='),
        'wp.pl/'                => array('szukai='),
        'online.onetcenter.org/' => array('qt='),
        'szukacz.pl/'           => array('q='),
        'yam.com/'              => array('k='),
        'pchome.com/'           => array('q='),
        'kvasir.no/'            => array('q='),
        'sesam.no/'             => array('q='),
        'ozu.es/'               => array('q='),
        'terra.com/'            => array('query='),
        'mynet.com/'            => array('q='),
        'ekolay.net/'           => array('q='),
        'rambler.ru/'           => array('words='),
        'yandex.com/'           => array('text='),
        'duckduckgo.com/'       => array('q='),
        'search.brave.com/'     => array('q='),
        'ecosia.org'            => array('q='),
        'qwant.com'             => array('q=')

    );

    protected $social_sources = array(

        'http://m.facebook.com'         => 'facebook',
        'https://m.facebook.com/'       => 'facebook',
        'https://l.facebook.com/'       => 'facebook',
        'facebook.com'                  => 'facebook',
        'fb.com'                        => 'facebook',
        'ig.com'                        => 'instagram',
        'https://l.instagram.com/'      => 'instagram',
        'instagram.com'                 => 'instagram',
        'reddit.com'                    => 'Reddit'

    );

    protected $referral_url_email_sources = array(
        'mailchi.mp'            => 'Mailchimp',
        'admin.mailchimp.com'   => 'Mailchimp',
        'campaign-archive.com'  => 'Mailchimp',
        'constantcontact.com'   => 'Constant Contact',
        'r20.rs6.net'           => 'Constant Contact',
        'klaviyo.com'           => 'Klaviyo',
        'klclick.com'           => 'Klaviyo',
        'r.mailjet.com'         => 'Brevo',
        'mta.brevo.com'         => 'Brevo',
        'sendibm3.com'          => 'Brevo',
        'hubspotlinks.com'      => 'HubSpot',
        'hs-analytics.net'      => 'HubSpot',
        'createsend.com'        => 'Campaign Monitor',
        'cmail'                 => 'Campaign Monitor', // matches cmail20.com, cmail30.com etc.
        'emltrk.com'            => 'ActiveCampaign',
        'activehosted.com'      => 'ActiveCampaign',
        'exacttarget.com'       => 'Salesforce Marketing Cloud',
        'mlsend.com'            => 'MailerLite',
        'emlml.com'             => 'MailerLite',
        'sendgrid.net'          => 'SendGrid',              // SendGrid tracking / links
        'sgizmo.com'            => 'SurveyGizmo / Alchemer',// occasionally used for email campaigns
        'omeda.com'             => 'Omeda',                 // media/marketing companies
        'email.tmtm.com'        => 'Emma / Campaign tracking',
        'sparkpostmail.com'     => 'SparkPost',             // SparkPost transactional emails
        'dotmailer.com'         => 'Dotdigital',            // Dotdigital/DM
    );

    protected $referral_url_ai_chat_sources = array(
        'perplexity.ai'            => 'Perplexity',
        'gemini.google.com'        => 'Gemini',
        'claude.ai'                => 'Claude',
        'deepseek.com'             => 'DeepSeek',
        'grok.com'                 => 'Grok',
        'openai.com'               => 'OpenAI',
        'anthropic.com'            => 'Anthropic',
        'bard.google.com'          => 'Bard',
        'chatgpt.com'              => 'ChatGPT'
    );

    /**
     *
     *  Referral URL
     *
     */
    public $referrer;

    /**
     *
     *  Query params array
     *
     */
    public $query_params = array();

    /**
     *
     *  Contructor
     *
     */
    public function __construct( $referrer, $query_params = array() ) {

        // Setup our referral URL
        $this->referrer = $referrer;
        $this->query_params = $query_params;

    }

    /**
     * 
     * 	A simple list of available traffic types to use in filtering
     * 
     * 	@return array $traffic_types in slug => name format.
     * 
     **/
    public static function available_traffic_types() {

        return array(

            'organic' 		=> 'Organic',
            'google_ads' 	=> 'Google Ads',
            'email' 		=> 'Email',
            'social' 		=> 'Social',
            'direct' 		=> 'Direct',
            'app' 			=> 'App',
            'referral' 		=> 'Referral',
            'ai_chat' 		=> 'AI Chat',
            'unknown' 		=> 'Unknown'
            
        );

    }

    /**
     *
     *  Lets start the process here
     *
     */
    public function determine_traffic_source() {

        $referral_url = $this->referrer;
        $result = 'Unknown'; // Default


        if ( $this->is_traffic_organic( $referral_url ) ) {

            $result = 'Organic';

        } elseif ( $this->is_traffic_paid_google( $referral_url ) ) {

            $result = 'Google Ads';

        } elseif ( $this->is_traffic_mail( $referral_url ) ) {

            $result = 'Email';

        } elseif ( $this->is_traffic_ai_chat( $referral_url ) ) {

            $result = 'AI Chat';

        } elseif ( $this->is_traffic_social( $referral_url ) ) {

            $result = 'Social';

        } elseif ( $this->is_traffic_direct( $referral_url ) ) {

            $result = 'Direct';

        } else {


            // Few manual checks on ref url
            $site_host = parse_url(site_url(), PHP_URL_HOST);
            $referring_domain = parse_url($referral_url, PHP_URL_HOST);

            if ( empty($referral_url) || $referring_domain === $site_host ) {
                $result = 'Direct';
            } elseif ( strpos($referral_url, 'app://') !== false ) {
                $result = 'App';
            } else {
                $result = 'Referral';
            }


        }

        // Run through query params if passed, these are a hard force provided they meet conditions.
        $query_param_check = $this->check_query_parameters();

        if ( $query_param_check ) {
            return $query_param_check;
        }

        return $result;

    }

    /**
     * 
     * Last thing, lets check query params if theyve been set and try to determine
     * 
     */
    public function check_query_parameters() {

        $query_params = $this->query_params;

        if (is_array($query_params) && !empty($query_params)) {

            foreach ($query_params as $key => $value) {
    
                $key   = strtolower($key);
                $value = strtolower(trim($value));
    
                // --- FACEBOOK / INSTAGRAM ---
                if (
                    $key === 'fbclid' ||
                    $key === 'fb_cid' ||
                    strpos($value, 'facebook') !== false ||
                    strpos($value, 'instagram') !== false ||
                    ($key === 'utm_source' && in_array($value, ['fb', 'ig', 'facebook', 'instagram'], true)) ||
                    ($key === 'utm_medium' && in_array($value, ['social', 'social_paid', 'paid_social'], true))
                ) {
                    return 'Social';
                }
    
                // --- GOOGLE ADS ---
                if (
                    $key === 'gclid' ||        // Auto-tagging (main)
                    $key === 'gbraid' ||       // iOS app-to-web
                    $key === 'wbraid' ||       // Web-to-app
                    $key === 'dclid' ||        // Display/Video 360
                    $key === 'google_cid' ||   // Custom tracking param
                    ($key === 'gclsrc' && strpos($value, 'aw.') === 0) || // Google click source (e.g. aw.ds)
                    ($key === 'utm_source' && $value === 'google' && isset($query_params['utm_medium']) && 
                        in_array(strtolower($query_params['utm_medium']), ['cpc', 'paid', 'ppc'], true))
                ) {
                    return 'Google Ads';
                }
    
                // --- EMAIL MARKETING ---
                if (
                    $key === 'mc_cid' || // Mailchimp
                    ($key === 'utm_medium' && $value === 'email') ||
                    ($key === 'utm_source' && in_array($value, ['mailpoet', 'mailchimp', 'campaignmonitor', 'sendgrid'], true))
                ) {
                    return 'Email';
                }
    
            }
        }

        return false;

    }

    /*
     * Check if source is organic
     * 
     * @param string $referrer The referrer page
     * 
     * @return true if organic, false if not
     */
    public function is_traffic_organic( $referrer ) {

        if ( is_string($referrer) && ! empty($referrer) ) {

            //Go through the organic sources
            foreach( $this->organic_sources as $searchEngine => $queries ) {

                //If referrer is part of the search engine key
                if ( strpos($referrer, $searchEngine) !== false) {

                    return true;

                }
            }

        }


        return false;
    }

    /*
     * Check if source is organic
     * 
     * @param string $referrer The referrer page
     * 
     * @return true if organic, false if not
     */
    public function is_traffic_direct( $referrer ) {

        if ( empty($referrer) || is_null($referrer) ) {

            return true;

        } else {

            return false;

        }

    }

        /*
     * Check if source is organic
     * 
     * @param string $referrer The referrer page
     * 
     * @return true if organic, false if not
     */
    public function is_traffic_paid_google( $referrer ) {

        return false;

    }

    /*
     * Check if source is organic
     * 
     * @param string $referrer The referrer page
     * 
     * @return true if organic, false if not
     */
    public function is_traffic_mail( $referrer ) {

        if ( is_string($referrer) && ! empty($referrer) ) {

            // Go through the organic sources
            foreach( $this->referral_url_email_sources as $email_source => $email_url ) {

                // If referrer is part of the search engine...
                if ( strpos( $referrer, $email_source ) !== false) {

                    return true;

                }
            }

        }

        return false;

    }

    /*
     * Check if source is organic
     * 
     * @param string $referrer The referrer page
     * 
     * @return true if organic, false if not
     */
    public function is_traffic_ai_chat( $referrer ) {

        if ( is_string($referrer) && ! empty($referrer) ) {

            // Go through the organic sources
            foreach( $this->referral_url_ai_chat_sources as $ai_chat_source => $ai_chat_url ) {

                // If referrer is part of the ai chat source...
                if ( strpos( $referrer, $ai_chat_source ) !== false) {

                    return true;

                }
            }

        }

        return false;

    }
        
    /*
     * Check if source is organic
     * 
     * @param string $referrer The referrer page
     * 
     * @return true if organic, false if not
     */
    public function is_traffic_social( $referrer ) {

        if ( is_string($referrer) && ! empty($referrer) ) {

            //Go through the organic sources
            foreach( $this->social_sources as $social_source => $social_platform ) {

                //If referrer is part of the search engine...
                if ( strpos( $referrer, $social_source ) !== false) {

                    return true;

                }
            }

        }

        return false;

    }

}