<?php
namespace MZKCatalog\RecordDriver;
use MZKCatalog\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Costumized record driver for Bookport records
 *
 */
class BookportSolrMarc extends ParentSolrDefault
{
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

    public function getNativeLinks()
    {
        return array();
    }

    public function supportsAjaxStatus()
    {
        return false;
    }
}
