<?php
// This file is part of Moodle-oembed-Filter
//
// Moodle-oembed-Filter is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle-oembed-Filter is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle-oembed-Filter.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filter for component 'filter_oembed'
 *
 * @package   filter_oembed
 * @copyright 2012 Matthew Cannings
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filters... 
 * Screencast (Mark Schall)
 * Soundcloud (Troy Williams) 
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');

class filter_oembed extends moodle_text_filter {

    public function setup($page, $context) {
        // This only requires execution once per request.
        static $jsinitialised = false;
        if (get_config('filter_oembed', 'lazyload')) {
            if (empty($jsinitialised)) {
                $page->requires->yui_module(
                        'moodle-filter_oembed-lazyload',
                        'M.filter_oembed.init_filter_lazyload',
                        array(array('courseid' => 0)));
                $jsinitialised = true;
            }
        }
    }

    public function filter($text, array $options = array()) {
        global $CFG;

        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }
//        if (get_user_device_type() !== 'default'){
            // no lazy video on mobile
            // return $text;

//        }
        if (stripos($text, '</a>') === false) {
            // Performance shortcut - all regexes below end with the </a> tag.
            // If not present nothing can match.
            return $text;
        }

        $newtext = $text; // We need to return the original value if regex fails!

        if (get_config('filter_oembed', 'youtube')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(youtube\.com|youtu\.be|youtube\.googleapis.com)\/(?:embed\/|v\/|watch\?v=|watch\?.+&amp;v=|watch\?.+&v=)?((\w|-){11})(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_youtubecallback', $newtext);
        }
        if (get_config('filter_oembed', 'vimeo')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(vimeo\.com)\/(\d+)(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_vimeocallback', $newtext);
        }
        if (get_config('filter_oembed', 'slideshare')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(slideshare\.net)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_slidesharecallback', $newtext);
        }
        if (get_config('filter_oembed', 'officemix')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(mix\.office\.com)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_officemixcallback', $newtext);
        }
        if (get_config('filter_oembed', 'issuu')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(issuu\.com)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_issuucallback', $newtext);
        }
        if (get_config('filter_oembed', 'screenr')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(screenr\.com)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_screenrcallback', $newtext);
        }
        if (get_config('filter_oembed', 'soundcloud')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(soundcloud\.com)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_soundcloudcallback', $newtext);
        }
        if (get_config('filter_oembed', 'ted')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(ted\.com)\/talks\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_tedcallback', $newtext);
        }
        if (get_config('filter_oembed', 'pollev')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.)?)(polleverywhere\.com)\/(polls|multiple_choice_polls|free_text_polls)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_pollevcallback', $newtext);
        }
        if (empty($newtext) or $newtext === $text) {
            // Error or not filtered.
            unset($newtext);
            return $text;
        }

        return $newtext;
    }
}

function filter_oembed_youtubecallback($link) {
    global $CFG;
//    $url = "https://gdata.youtube.com/feeds/api/videos/".trim($link[4])."?v=2";
    $url = "http://www.youtube.com/oembed?url=".trim($link[1]).trim($link[3]).'/watch?v='.trim($link[4])."&format=json";
    $jsonret = filter_oembed_curlcall($url);
    return filter_oembed_vidembed($jsonret);
}

function filter_oembed_vimeocallback($link) {
    global $CFG;
    $url = "http://vimeo.com/api/oembed.json?url=".trim($link[1]).trim($link[2]).trim($link[3]).'/'.trim($link[4]).'&maxwidth=480&maxheight=270';
    $jsonret = filter_oembed_curlcall($url);
    return filter_oembed_vidembed($jsonret);
}

function filter_oembed_tedcallback($link) {
    global $CFG;
    $url = "http://www.ted.com/services/v1/oembed.json?url=".trim($link[1]).trim($link[3]).'/talks/'.trim($link[4]).'&maxwidth=480&maxheight=270';
    $jsonret = filter_oembed_curlcall($url);
    return filter_oembed_vidembed($jsonret);
}

function filter_oembed_slidesharecallback($link) {
    global $CFG;
    $url = "http://www.slideshare.net/api/oembed/2?url=".trim($link[1]).trim($link[3]).'/'.trim($link[4])."&format=json&maxwidth=480&maxheight=270";
    $json = filter_oembed_curlcall($url);
    return $json === null ? '<h3>Error in loading iframe. Please try refreshing the page.</h3>' : $json['html'];
}

function filter_oembed_officemixcallback($link) {
    global $CFG;
    $url = "https://mix.office.com/oembed/?url=".trim($link[1]).trim($link[2]).trim($link[3]).'/'.trim($link[4]);
    $json = filter_oembed_curlcall($url);

    if($json === null){
        return '<h3>Error in loading video. Please try refreshing the page.</h3>';
    }

    // Increase the height and width of iframe.
    $json['html'] = str_replace('width="348"', 'width="480"', $json['html']);
    $json['html'] = str_replace('height="245"', 'height="320"', $json['html']);
    $json['html'] = str_replace('height="310"', 'height="410"', $json['html']);
    $json['html'] = str_replace('height="267"', 'height="350"', $json['html']);
    return filter_oembed_vidembed($json);
}

function filter_oembed_pollevcallback($link) {
    global $CFG;
    $url = "http://www.polleverywhere.com/services/oembed?url=".trim($link[1]).trim($link[3]).'/'.trim($link[4]).'/'.trim($link[5])."&format=json&maxwidth=480&maxheight=270";
    $json = filter_oembed_curlcall($url);
    return $json === null ? '<h3>Error in loading iframe. Please try refreshing the page.</h3>' : $json['html'];
}

function filter_oembed_issuucallback($link) {
    global $CFG;
    $url = "http://issuu.com/oembed?url=".trim($link[1]).trim($link[3]).'/'.trim($link[4])."&format=json";
    $json = filter_oembed_curlcall($url);
    return $json === null ? '<h3>Error in loading iframe. Please try refreshing the page.</h3>' : $json['html'];
}

function filter_oembed_screenrcallback($link) {
    global $CFG;
    $url = "http://www.screenr.com/api/oembed.json?url=".trim($link[1]).trim($link[3]).'/'.trim($link[4]).'&maxwidth=480&maxheight=270';
    $json = filter_oembed_curlcall($url);
    return filter_oembed_vidembed($json);
}

function filter_oembed_soundcloudcallback($link) {
    global $CFG;
    $url = "http://soundcloud.com/oembed?url=".trim($link[1]).trim($link[3]).'/'.trim($link[4])."&format=json&maxwidth=480&maxheight=270'";
    $json = filter_oembed_curlcall($url);
    return filter_oembed_vidembed($json);
}

function filter_oembed_curlcall($www) {
    $crl = curl_init();
    $timeout = 15;
    curl_setopt ($crl, CURLOPT_URL, $www);
    curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt ($crl, CURLOPT_SSL_VERIFYPEER, false);
    $ret = curl_exec($crl);

    if ($ret === false) {
        $errorno = curl_errno($crl);
        if (in_array($errorno, array('6', '7', '28'))) {

            for ($i = 0; $i < 3; $i++) {
                $ret = curl_exec($crl);

                if ($ret !== false || !in_array(curl_errno($crl), array('6', '7', '28'))) {
                    break;
                }
            }

            if ($ret === false) {
                return null;
            }

        } else {
            return null;
        }
    }

    curl_close($crl);
    $result = json_decode($ret, true);
    return $result;
}

function filter_oembed_vidembed($json) {

    if ($json === null) {
        return '<h3>Error in loading video. Please try refreshing the page.</h3>';
    }

    if (get_config('filter_oembed', 'lazyload')) {

        $dom = new DOMDocument();

        // To surpress the loadHTML Warnings.
        libxml_use_internal_errors(true);
        $dom->loadHTML($json['html']);
        libxml_use_internal_errors(false);

        // Get height and width of iframe.
        $height = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('height');
        $width = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('width');

        $embedcode = '<a class="lvoembed lvvideo" data-embed="'.htmlspecialchars($json['html']).'"';
        $embedcode .= 'href="#" data-height="'. $height .'" data-width="'. $width .'"><div class="lazyvideo_container">';
        $embedcode .= '<img class="lazyvideo_placeholder" src="'.$json['thumbnail_url'].'" />';
        $embedcode .= '<div class="lazyvideo_title"><div class="lazyvideo_text">'.$json['title'].'</div></div>';
        $embedcode .= '<span class="lazyvideo_playbutton"></span>';
        $embedcode .= '</div></a>';
    } else {
        $embedcode = $json['html'];
    }
    return $embedcode;
}