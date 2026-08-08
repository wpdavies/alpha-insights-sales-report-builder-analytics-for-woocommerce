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
class WPDAI_Traffic_Type_Detection {

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
            'microsoft_ads' => 'Microsoft Ads',
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
     *  Map a traffic source display name to a CSS class slug.
     *
     *  @param string $traffic_source_name Traffic source label (e.g. "Google Ads").
     *  @return string CSS-safe slug (e.g. "google_ads").
     *
     */
    public static function traffic_source_css_class( $traffic_source_name ) {

        static $lookup = null;

        if ( null === $lookup ) {
            $lookup = array_flip( self::available_traffic_types() );
            $lookup['Admin'] = 'admin';
        }

        if ( is_string( $traffic_source_name ) && isset( $lookup[ $traffic_source_name ] ) ) {
            return $lookup[ $traffic_source_name ];
        }

        return sanitize_title( is_string( $traffic_source_name ) ? $traffic_source_name : '' );
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
            $site_host = wp_parse_url(site_url(), PHP_URL_HOST);
            $referring_domain = !empty($referral_url) ? wp_parse_url($referral_url, PHP_URL_HOST) : false;

            if ( empty($referral_url) || $referring_domain === $site_host ) {
                $result = 'Direct';
            } elseif ( is_string($referral_url) && strpos($referral_url, 'app://') !== false ) {
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
     * Normalize landing-page query params to lowercase string keys/values.
     *
     * @param array<string, mixed> $query_params Raw query params.
     * @return array<string, string>
     */
    private function normalize_query_params( $query_params ) {

        $normalized = array();

        if ( ! is_array( $query_params ) || empty( $query_params ) ) {
            return $normalized;
        }

        foreach ( $query_params as $key => $value ) {
            if ( is_array( $value ) ) {
                continue;
            }

            $key = strtolower( (string) $key );
            $value = strtolower( trim( (string) $value ) );

            if ( '' === $key || '' === $value ) {
                continue;
            }

            $normalized[ $key ] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $query_params Normalized query params.
     * @param string                $key          Param key.
     * @return string
     */
    private function get_normalized_query_param( $query_params, $key ) {

        $key = strtolower( $key );

        return isset( $query_params[ $key ] ) ? $query_params[ $key ] : '';
    }

    /**
     * Paid search / social / email UTM sources used when referrer is missing.
     *
     * @return array<string, array<int, string>>
     */
    private function get_query_param_source_lists() {

        return array(
            'google_ads_sources'    => array( 'google', 'googleads', 'google_ads', 'adwords' ),
            'microsoft_ads_sources' => array( 'bing', 'microsoft', 'msft', 'msn' ),
            'social_sources'        => array(
                'fb', 'ig', 'facebook', 'instagram', 'meta',
                'tiktok', 'tt',
                'linkedin', 'li',
                'pinterest', 'pin',
                'twitter', 'x',
                'snapchat', 'snap',
                'youtube', 'yt',
                'reddit',
            ),
            'email_sources'         => array(
                'mailpoet', 'mailchimp', 'campaignmonitor', 'sendgrid',
                'klaviyo', 'omnisend', 'brevo', 'sendinblue', 'hubspot',
                'activecampaign', 'mailerlite', 'dotdigital', 'constantcontact',
                'drip', 'convertkit', 'aweber', 'getresponse', 'mailjet',
            ),
            'paid_mediums'          => array( 'cpc', 'ppc', 'paid', 'paidsearch', 'paid_search' ),
            'social_mediums'        => array( 'social', 'social_paid', 'paid_social' ),
        );
    }

    /**
     * Last pass: check landing-page query params to rescue attribution when referrer is empty.
     *
     * @return string|false Traffic source label or false when inconclusive.
     */
    public function check_query_parameters() {

        $query_params = $this->normalize_query_params( $this->query_params );

        if ( empty( $query_params ) ) {
            return false;
        }

        $lists  = $this->get_query_param_source_lists();
        $source = $this->get_normalized_query_param( $query_params, 'utm_source' );
        $medium = $this->get_normalized_query_param( $query_params, 'utm_medium' );

        // --- Click IDs (highest confidence) ---
        if (
            isset( $query_params['gclid'] )
            || isset( $query_params['gbraid'] )
            || isset( $query_params['wbraid'] )
            || isset( $query_params['dclid'] )
            || isset( $query_params['google_cid'] )
            || ( isset( $query_params['gclsrc'] ) && 0 === strpos( $query_params['gclsrc'], 'aw.' ) )
        ) {
            return 'Google Ads';
        }

        if ( isset( $query_params['msclkid'] ) ) {
            return 'Microsoft Ads';
        }

        if (
            isset( $query_params['fbclid'] )
            || isset( $query_params['fb_cid'] )
            || isset( $query_params['meta_cid'] )
            || isset( $query_params['ttclid'] )
            || isset( $query_params['li_fat_id'] )
        ) {
            return 'Social';
        }

        if ( isset( $query_params['srsltid'] ) ) {
            return 'Organic';
        }

        // --- Email platform click IDs ---
        if (
            isset( $query_params['mc_cid'] )
            || isset( $query_params['mc_eid'] )
            || isset( $query_params['_kx'] )
        ) {
            return 'Email';
        }

        // --- UTM medium ---
        if ( 'email' === $medium ) {
            return 'Email';
        }

        if ( in_array( $medium, $lists['social_mediums'], true ) ) {
            return 'Social';
        }

        if ( in_array( $medium, $lists['paid_mediums'], true ) ) {
            if ( in_array( $source, $lists['google_ads_sources'], true ) ) {
                return 'Google Ads';
            }
            if ( in_array( $source, $lists['microsoft_ads_sources'], true ) ) {
                return 'Microsoft Ads';
            }
            if ( in_array( $source, $lists['social_sources'], true ) ) {
                return 'Social';
            }
        }

        // --- UTM source ---
        if ( in_array( $source, $lists['email_sources'], true ) ) {
            return 'Email';
        }

        if ( in_array( $source, $lists['social_sources'], true ) ) {
            return 'Social';
        }

        if ( in_array( $source, $lists['google_ads_sources'], true ) && in_array( $medium, $lists['paid_mediums'], true ) ) {
            return 'Google Ads';
        }

        if ( in_array( $source, $lists['microsoft_ads_sources'], true ) && in_array( $medium, $lists['paid_mediums'], true ) ) {
            return 'Microsoft Ads';
        }

        // --- Substring hints in any param value (legacy loop behaviour) ---
        foreach ( $query_params as $key => $value ) {
            if (
                false !== strpos( $value, 'facebook' )
                || false !== strpos( $value, 'instagram' )
                || false !== strpos( $value, 'tiktok' )
                || false !== strpos( $value, 'linkedin' )
                || false !== strpos( $value, 'pinterest' )
                || false !== strpos( $value, 'twitter' )
            ) {
                return 'Social';
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