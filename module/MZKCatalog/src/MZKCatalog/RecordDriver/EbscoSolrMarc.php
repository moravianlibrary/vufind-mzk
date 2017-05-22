<?php
namespace MZKCatalog\RecordDriver;
use MZKCatalog\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Costumized record driver for EBSCO records
 *
 */
class EbscoSolrMarc extends ParentSolrDefault
{

    const EZPROXY_URL = 'https://proxy.mzk.cz/login?auth=shibboleth&url=';

    public function getTitle()
    {
        return isset($this->fields['title_display']) ?
        $this->fields['title_display'] : '';
    }

    // FIXME: fix on the RecordManager side?
    public function getShortTitle()
    {
        $shortTitle = parent::getShortTitle();
        if (empty($shortTitle)) {
            $shortTitle = $this->getTitle();
        }
        return $shortTitle;
    }

    public function getEODLink()
    {
        return null;
    }

    public function isAvailableForDigitalization()
    {
        return false;
    }

    public function getRealTimeHoldings()
    {
        return array();
    }

    public function getRestrictions()
    {
        return array();
    }

    public function getSubscribedYears()
    {
        return null;
    }

    public function getSubscribedVolumes()
    {
        return null;
    }

    public function getNumberOfHoldings()
    {
        return null;
    }

    public function getItemLinks()
    {
        return array();
    }

    public function getAllSubjectHeadings()
    {
        return array();
    }

    public function getURLs()
    {
        $links = parent::getURLs();
        foreach ($links as &$link) {
            $link['url'] =  self::EZPROXY_URL . $link['url'];
        }
        return $links;
    }

}