<?php

class getSiteSummary {

    private $ngram = array();
    private $egram = array();
    private $charset = 'auto';
    private $disabledTag = array(
        'script',
        'style',
        'iframe',
        'noscript',
        'h1',
        'code',
        'pre'
    );
    private $url = null;
    private $threshold = array(2, 6);

    function __construct($url, $charset = null)
    {
        $this->url = $url;
        if (preg_match('/^(sjis)|(shift_jis)$/i', $charset)) {
            $this->charset = 'SJIS';
        } elseif (preg_match('/^euc-jp$/i', $charset)) {
            $this->charset = 'EUC-JP';
        } elseif (preg_match('/^utf-8$/i', $charset)) {
            $this->charset = 'UTF-8';
        }
    }

    public function fetch()
    {
        $dom = $this->dom($this->url);
        if (!$dom) {
            return ;
        }
        $title = strip_tags($this->getTitle($dom));
        $summary = strip_tags($this->getSummary($dom, $this->url));
        return array('title'=>$title, 'summary'=>$summary);
    }

    private function getSummary($dom)
    {
        $texts = array();
        $nodes = $dom->getElementsByTagName('br');
        foreach ($nodes as $node) {
            $text = $this->getNextRows($node->previousSibling);
            if (strlen($text)) {
                $texts[] = $text;
            }
        }
        $nodes = $dom->getElementsByTagName('p');
        foreach ($nodes as $node) {
            $text = $this->getNextRows($node);
            if (strlen($text)) {
                $texts[] = $text;
            }
        }
        usort($texts, array(&$this, 'cmp'));
        $summary = $texts[0];

        $summary = strip_tags($summary);
        $summary = mb_convert_kana($summary, 'asKV');
        $summary = trim($summary);
        return $summary;
    }

    private function getNextRows($node)
    {
        $text = null;
        $current = $node;
        while (isset($current->nodeValue) && $current->nodeValue) {
            if (isset($current->tagName)) {
                $tag = $current->tagName;
            } else {
                $tag = null;
            }
            $type = $current->nodeType;
            if ($type == XML_TEXT_NODE) {
                $text .= $current->nodeValue;
                if (isset($current->nextSibling)) {
                    $current = $current->nextSibling;
                    continue;
                } else {
                    break;
                }
            } elseif ($tag == 'p') {
                $text .= $current->textContent;
                if (isset($current->nextSibling)) {
                    $current = $current->nextSibling;
                    continue;
                } else {
                    break;
                }
            } elseif ($tag == 'br') {
                if (isset($current->nextSibling)) {
                    $current = $current->nextSibling;
                    continue;
                } else {
                    break;
                }
            } else {
                break;
            }
        }
        return $text;
    }

    private function cmp($a, $b)
    {
        $lena = strlen($a);
        $lenb = strlen($b);
        $a = $this->getTextScore($a);
        $b = $this->getTextScore($b);
        if ($a == $b) {
            return ($lena < $lenb) ? 1 : -1;
        }
        return ($a < $b) ? 1 : -1;
    }

    private function getTextScore($text)
    {
        if (!$text) {
            return 0;
        }
        $text = strtolower($text);
        $score = 0;
        foreach ($this->ngram as $n) {
            $score = $score + mb_substr_count($text, $n); // noisy?
        }
        foreach ($this->egram as $n) {
            $score = $score - mb_substr_count($text, $n); // noisy?
        }
        return $score;
    }

    private function getTitle($dom)
    {
        $this->ngram = array();
        $this->egram = array();
        $title = $dom->getElementsByTagName('title')->item(0)->nodeValue;
        $title = trim($title);
        $lower = strtolower($title);
        for ($i=0; $i<mb_strlen($title); $i++) {
            $this->ngram[] = mb_substr($lower, $i, $this->threshold[0]);
            $this->egram[] = mb_substr($lower, $i, $this->threshold[1]);
        }
        return $title;
    }

    private function dom($url)
    {
        if (!$html = @file_get_contents($url)) {
            return;
        }
        if (!$this->checkContentType($http_response_header)) {
            return false;
        }
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', $this->charset);
        $html = preg_replace('/<!\-\-.*?\-\->/s', '', $html);
        foreach ($this->disabledTag as $tag) {
            $html = $this->deleteTag($html, $tag);
        }
        $dom = new DOMDocument();
        $dom->recover = true;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_use_internal_errors(false);

        return $dom;
    }

    private function deleteTag($html, $tag)
    {
        $reg = "/\<$tag(\s.*?)?\>.*?\<\/{$tag}\>/is";
        return preg_replace($reg, '', $html);
    }

    private function checkContentType($res)
    {
        foreach ($res as $r) {
            if (preg_match('/^content\-type:\s+text\/html/i', $r)) {
                return true;
            }
        }
        return false;
    }
}

?>
