<?php
/*
Plugin Name: url2link
Plugin URI: http://firegoby.theta.ne.jp/wp/url2link
Description: Embed link with title and summary only the URL as input.
Author: Takayuki Miyauchi (THETA NETWORKS Co,.Ltd)
Version: 0.2.4
Author URI: http://firegoby.theta.ne.jp/
*/

/*
Copyright (c) 2010 Takayuki Miyauchi (THETA NETWORKS Co,.Ltd).

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

require_once(dirname(__FILE__).'/includes/getSiteSummary.php');

new url2link();

class url2link {

    private $length = 150;
    private $meta_id = '_url2link_';
    private $debug = false;

    function __construct()
    {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            $this->debug = true;
        }
        wp_embed_register_handler(
            'url2link',
            '#https?://.+#i',
            array(&$this, 'handler'),
            9999
        );
        add_action(
            'wp_head',
            array(&$this, 'loadCSS')
        );
        add_shortcode('url2link', array(&$this, 'shortcode'));
        add_action('save_post', array(&$this, 'delete_oembed_caches'));
        add_filter('plugin_row_meta', array(&$this, 'plugin_row_meta'), 10, 2);
    }

    public function handler($m, $attr, $url, $rattr)
    {
        return '[url2link url="'.$url.'"]';
    }

    private function getCache($id, $key)
    {
        if ($this->debug) {
            return false;
        } else {
            return get_post_meta($id, $key, true);
        }
    }

    public function shortcode($p)
    {
        global $post;
        $url =  html_entity_decode($p['url']);
        if ($html = $this->getCache($post->ID, $this->meta_id.$url)) {
            // since  ver 0.2
        } elseif ($html = $this->getCache($post->ID, '_'.$url)) {
            // for ver 0.1
            delete_post_meta($post->ID, '_'.$url);
            add_post_meta($post->ID, $this->meta_id.$url, $html);
        } else {
            $summary = '';
            if (isset($p['summary']) && $p['summary']) {
                if (isset($p['charset']) && $p['charset']) {
                    $obj = new getSiteSummary($url, $p['charset']);
                } else {
                    $obj = new getSiteSummary($url);
                }
                $site = $obj->fetch();
                if (!$site) {
                    return ;
                }
                $link = sprintf('<a href="%s">%s</a>', $url, $site['title']);
                $summary = esc_html($p['summary']);
            } elseif (strpos($url, site_url()) > -1) {
                $pid = url_to_postid($url);
                $pp = get_post($pid);
                $link = sprintf('<a href="%s">%s</a>', $url, $pp->post_title);
                $summary = strip_tags($pp->post_content);
            } else {
                if (isset($p['charset']) && $p['charset']) {
                    $obj = new getSiteSummary($url, $p['charset']);
                } else {
                    $obj = new getSiteSummary($url);
                }
                $site = $obj->fetch();
                if (!$site) {
                    return ;
                }
                $link = sprintf('<a href="%s">%s</a>', $url, $site['title']);
                $summary = $site['summary'];
            }
            if ($summary) {
                if (isset($p['length']) && is_int($p['length']) && $p['length']) {
                    $this->length = $p['length'];
                }
                $summary = mb_substr($summary, 0, $this->length).'...';
                $summary = "<div class=\"link_summary\">{$summary}</div>";
            }
            $html = sprintf($this->gethtml(), $link, $summary);
            add_post_meta($post->ID, $this->meta_id.$url, $html);
        }
        return $html;
    }

    private function gethtml()
    {
        $html =<<<EOL
<div class="url2link">
    <div class="link_title">%s</div>
    %s
</div>
EOL;
        return $html;
    }

    public function delete_oembed_caches( $post_ID ) {
        $post_metas = get_post_custom_keys( $post_ID );
        if (empty($post_metas))
            return;

        foreach ($post_metas as $post_meta_key) {
            if ($this->meta_id == substr( $post_meta_key, 0, strlen($this->meta_id)))
                delete_post_meta( $post_ID, $post_meta_key );
        }
    }

    public function loadCSS()
    {
        $style = get_template_directory().'/url2link.css';
        if (is_file($style)) {
            $style = get_bloginfo('stylesheet_directory').'/url2link.css';
        } else {
            $url = WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__));
            $style = $url.'/url2link.css';
        }
        echo "<!--url2link plugin-->\n";
        echo '<link rel="stylesheet" type="text/css" media="all" href="'.$style.'">';
    }

    public function plugin_row_meta($links, $file)
    {
        $pname = plugin_basename(__FILE__);
        if ($pname === $file) {
            $links[] = '<a href="http://firegoby.theta.ne.jp/pfj/">Pray for Japan</a>';
        }
        return $links;
    }
}

?>
