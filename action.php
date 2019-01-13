<?php

/**
 * Bloglinks Plugin: displays a link to the previous and the next blog entry above posts in configured namespaces
 * 
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Gina Haeussge <osd@foosel.net>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC'))
    die();

if (!defined('DOKU_LF'))
    define('DOKU_LF', "\n");
if (!defined('DOKU_TAB'))
    define('DOKU_TAB', "\t");
if (!defined('DOKU_PLUGIN'))
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once (DOKU_PLUGIN . 'action.php');
require_once(DOKU_INC . 'inc/pageutils.php');

class action_plugin_bloglinks extends DokuWiki_Action_Plugin {

    /**
     * Register the eventhandlers.
     */
    function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'handle_act_render', array ());
        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, 'handle_metaheader_output', array ());
    }

    function handle_metaheader_output(Doku_Event $event, $params) {
        global $ACT;

        if ($ACT != 'show')
            return;

        $namespace = $this->_getActiveNamespace();
        if (!$namespace)
            return;

        $relatedEntries = $this->_getRelatedEntries($namespace);

        if (isset ($relatedEntries['prev'])) {
            $event->data['link'][] = array (
                'rel' => 'prev',
                'href' => wl($relatedEntries['prev']['id'], '')
            );
        }
        if (isset ($relatedEntries['next'])) {
            $event->data['link'][] = array (
                'rel' => 'next',
                'href' => wl($relatedEntries['next']['id'], '')
            );
        }

        return true;
    }

    function handle_act_render(Doku_Event $event, $params) {
        global $ACT;

        if ($ACT != 'show')
            return;

        $namespace = $this->_getActiveNamespace();
        if ($namespace)
            $this->_printLinks($this->_getRelatedEntries($namespace));

        return true;
    }

    function _getActiveNamespace() {
        global $ID;
        global $INFO;

        $pattern = $this->getConf('excluded_pages');
        if (strlen($pattern) > 0 && preg_match($pattern, $ID)) {
            return false;
        }

        if (!$INFO['exists'])
            return false;

        $namespaces = explode(',', $this->getConf('enabled_namespaces'));
        $current_id_parts = explode(':', getNS($ID));
        foreach ($namespaces as $namespace) {
            $namespaceparts = explode(':', $namespace);

            // number of namespace elements must be the same
            if (count($namespaceparts) != count($current_id_parts)) {
                continue;
            }
            
            // mutching each elements in the order
            for ($i = 0 ; $i < count($namespaceparts) ; $i++ ) {
                if (!preg_match("/$namespaceparts[$i]/",$current_id_parts[$i])) {
                    continue 2;
                } 
            }
            return getNS($ID);
        }
        return false;
    }

    function _getRelatedEntries($namespace) {
        global $ID;

        // get the blog entries for the namespace
        if ($my = & plugin_load('helper', 'blog'))
            $entries = $my->getBlog($namespace);

        if (!$entries)
            return;

        // normalize keys
        $entries = array_values($entries);

        // prepare search for current page
        $meta = p_get_metadata($ID);
        if ($my->sort == 'mdate') {
            $date = $meta['date']['modified'];
            if (!$date) $date = filemtime(wikiFN($ID));
        } else {
            $date = $meta['date']['created'];
            if (!$date) $date = filectime(wikiFN($ID));
        }
        $perm = auth_quickaclcheck($ID);
        $curPage = array (
            'id' => $ID,
            'title' => $meta['title'],
            'date' => $date,
            'user' => $meta['creator'],
            'desc' => $meta['description']['abstract'],
            'exists' => true,
            'perm' => $perm,
            'draft' => ($meta['type'] == 'draft'),
        );
        
        // get index of current page 
        $curIndex = array_search($curPage, $entries);

        // get previous and next entries
        if ($curIndex > 0 && curIndex < count($entries) - 1) { // got a prev and a next
            list ($next, $cur, $prev) = array_slice($entries, $curIndex -1, 3);
        } else if ($curIndex == 0) { // only got a prev
            list ($cur, $prev) = array_slice($entries, $curIndex, 2);
        } else { // only got a next
            list ($next, $cur) = array_slice($entries, $curIndex -1, 2);
        }

        return array('prev' => $prev, 'cur' => $cur, 'next' => $next);
    }

    /**
     * Prints the links to the related entries
     */
    function _printLinks($relatedEntries) {
        // display links
        echo '<div id="plugin_bloglinks__links">' . DOKU_LF;
        
        foreach(array('prev', 'next') as $type) {
            if (isset ($relatedEntries[$type])) {
                echo '<div class="plugin_bloglinks__'.$type.'">';
                echo '<a href="' . wl($relatedEntries[$type]['id'], '') . '" class="wikilink1" rel="'.$type.'">' . $this->_linkTemplate($relatedEntries[$type], $type) . '</a>';
                echo '</div>';
            }
        }
        echo DOKU_LF . '</div>';
    }
    
    function _linkTemplate($entry, $type) {
        global $conf;
        
        $replace = array(
            '@@TITLE@@' => $entry['title'],
            '@@AUTHOR@@' => $entry['user'],
            '@@DATE@@' => date($conf['dformat'], $entry['date']),
            '@@NAME@@' => $this->getLang($type . '_link'),
        );
        
        $linktext = $this->getConf($type.'_template');
        $linktext = str_replace(array_keys($replace), array_values($replace), $linktext);
        return $linktext;
    }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
