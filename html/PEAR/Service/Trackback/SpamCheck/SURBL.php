<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Services_Trackback_SpamCheck_SURBL.
 *
 * This spam detection module for Services_Trackback utilizes SUR
 * blacklists for detection of URLs used in spam.
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Webservices
 * @package   Trackback
 * @author    Tobias Schlitt <toby@php.net>
 * @copyright 2005-2006 The PHP Group
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   CVS: $Id: SURBL.php,v 1.9 2008/10/01 13:22:11 clockwerx Exp $
 * @link      http://pear.php.net/package/Services_Trackback
 * @since     File available since Release 0.5.0
 */

     // {{{ require_once

/**
 * Load PEAR error handling
 */
require_once 'PEAR.php';

/**
 * Load SpamCheck base.
 */
require_once 'Services/Trackback/SpamCheck.php';

/**
 * Load Net_SURBL for spam cheching
 */
require_once 'Net/DNSBL/SURBL.php';

    // }}}

/**
 * SURBL
 * Module for spam detecion using SURBL.
 *
 * @category  Webservices
 * @package   Trackback
 * @author    Tobias Schlitt <toby@php.net>
 * @copyright 2005-2006 The PHP Group
 * @license   http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version   Release: 0.6.2
 * @link      http://pear.php.net/package/Services_Trackback
 * @since     0.5.0
 * @access    public
 */
class Services_Trackback_SpamCheck_SURBL extends Services_Trackback_SpamCheck
{

    // {{{ _options

    /**
     * Options for the SpamCheck.
     *
     * @var array
     * @since 0.5.0
     * @access protected
     */
    var $_options = array(
        'continuous'    => false,
        'sources'       => array(
            'multi.surbl.org'
        ),
        'elements'      => array(
            'url',
            'title',
            'excerpt',
        ),
    );

    // }}}
    // {{{ _surbl

    /**
     * The Net_DNSBL_SURBL object for checking.
     *
     * @var object(Net_DNSBL_SURBL)
     * @since 0.5.0
     * @access protected
     */
    var $_surbl;

    // }}}
    // {{{ _urls

    /**
     * URLs extracted from the trackback.
     *
     * @var array
     * @access private
     * @since 0.5.0
     */
    var $_urls = array();

    // }}}
    // {{{ Services_Trackback_SpamCheck_SURBL()

    /**
     * Constructor.
     * Create a new instance of the SURBL spam protection module.
     *
     * @param array $options An array of options for this spam protection module.
     *                       General options are
     *                       'continuous':  Whether to continue checking more
     *                                      sources, if a match has been found.
     *                       'sources':     List of blacklist servers. Indexed.
     *                       'elements'     Array of trackback data fields
     *                                      extract URLs from (standard is 'title'
     *                                      and 'excerpt').
     *
     * @since 0.5.0
     * @access public
     * @return Services_Trackback_SpamCheck_SURBL The newly created SpamCheck object.
     */
    function Services_Trackback_SpamCheck_SURBL($options = null)
    {
        if (is_array($options)) {
            foreach ($options as $key => $val) {
                $this->_options[$key] = $val;
            }
        }
        $this->_surbl = new Net_DNSBL_SURBL();
    }

    // }}}
    // {{{ reset()

    /**
     * Reset results.
     * Reset results to reuse SpamCheck.
     *
     * @since 0.5.0
     * @static
     * @access public
     * @return null
     */
    function reset()
    {
        parent::reset();
        $this->_urls  = array();
        $this->_surbl = new Net_DNSBL_SURBL();
    }

    // }}}
    // {{{ _checkSource()

    /**
     * Check a specific source if a trackback has to be considered spam.
     *
     * @param mixed              $source    Element of the _sources array to check.
     * @param Services_Trackback $trackback The trackback to check.
     *
     * @since 0.5.0
     * @access protected
     * @abstract
     * @return bool True if trackback is spam, false, if not, PEAR_Error on error.
     */
    function _checkSource($source, $trackback)
    {
        if (count($this->_urls) == 0) {
            $this->_extractURLs($trackback);
        }
        $this->_surbl->setBlacklists(array($source));
        $spam = false;
        foreach ($this->_urls as $url) {
            $spam = ($spam || $this->_surbl->isListed($url));
            if ($spam) {
                break;
            }
        }
        return $spam;
    }

    // }}}
    // {{{ _extractURLs()
    /**
     * Extract all URLS from the Trackback
     *
     * @param Services_Trackback $trackback The trackback to extract urls from.
     *
     * @return void
     */
    function _extractURLs($trackback)
    {
        $matches = array();

        $urls = '(?:http|file|ftp)';
        $ltrs = 'a-z0-9';
        $gunk = '.-';
        $punc = $gunk;
        $any  = "$ltrs$gunk";

        $regex = "{
                      $urls   ://
                      [$any]+


                      (?=
                        [$punc]*
                        [^$any]
                      |
                      )
                  }x";
        foreach ($this->_options['elements'] as $element) {
            if (0 !== preg_match_all($regex, $trackback->get($element), $matches)) {
                foreach ($matches[0] as $match) {
                    $this->_urls[md5($match)] = $match;
                }
            }
        }
    }

    // }}}

}
