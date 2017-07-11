<?php
namespace MZKCatalog\RecordDriver;
use VuFind\RecordDriver\SolrMarc As ParentSolrDefault;

/*
 * Costumized record driver for MZK
 *
 */
class SolrMarc extends ParentSolrDefault
{

    protected $numberOfHoldings;

    const ALEPH_BASE_URL = "http://aleph.mzk.cz/";

    public function getAllSubjectHeadings()
    {
        $result = array();
        $subjectFields = array('topic', 'geographic', 'genre');
        foreach ($subjectFields as $field) {
            if (isset($this->fields[$field])) {
                $result = array_merge($result, $this->fields[$field]);
            }
        }
        return $result;
    }

    public function getTitle()
    {
        return isset($this->fields['title_display']) ?
            $this->fields['title_display'] : '';
    }

    public function getHoldingFilters()
    {
        return $this->ils->getDriver()->getHoldingFilters($this->getUniqueID());
    }

    public function getAvailableHoldingFilters()
    {
        return array(
            'year' => array('type' => 'select', 'keep' => array('hide_loans')),
            'volume' => array('type' => 'select', 'keep' => array('hide_loans')),
            'hide_loans' => array('type' => 'checkbox', 'keep' => array('year', 'volume')),
        );
    }

    public function getNumberOfHoldings() {
        if (!isset($this->numberOfHoldings)) {
            $this->numberOfHoldings = count($this->marcRecord->getFields('996'));
        }
        return $this->numberOfHoldings;
    }

    public function getRealTimeHoldings($filters = array())
    {
        $holdings = $this->hasILS()
        ? $this->holdLogic->getHoldings($this->getUniqueID(), $filters)
        : array();
        foreach ($holdings as &$holding) {
            $holding['duedate_status'] = $this->translateHoldingStatus($holding['status'],
                $holding['duedate_status']);
        }
        return $holdings;
    }

    public function getEODLink()
    {
        $eod = isset($this->fields['statuses']) && in_array('available_for_eod', $this->fields['statuses']);
        if (!$eod) {
            return null;
        }
        list($base, $sysno) = explode('-', $this->getUniqueID());
        $eodLinks = array(
            'MZK01' => 'http://books2ebooks.eu/odm/orderformular.do?formular_id=133&sys_id=',
            'MZK03' => 'http://books2ebooks.eu/odm/orderformular.do?formular_id=131&sys_id=',
        );
        $link = $eodLinks[$base] . $sysno;
        return $link;
    }

    public function isDigitized()
    {
        foreach ($this->getFieldArray('856', array('y', 'z'), false) as $onlineAccessText) {
            $onlineAccessText = strtolower($onlineAccessText);
            if (strpos($onlineAccessText, 'digitaliz') !== false) {
                return true;
            }
        }
        return false;
    }

    public function isAvailableForDigitalization()
    {
        return $this->getEODLink() == null
            && substr($this->getUniqueID(), 0, 5) == 'MZK01'
            && substr($this->marcRecord->getField('008'), 23, 2) == 'xr'
            && in_array('Book', $this->getFormats())
            && !$this->isDigitized()
        ;
    }

    public function getRestrictions()
    {
        list($base, $sysno) = explode('-', $this->getUniqueID());
        $result = $this->getFieldArray('540', array('a'));
        if (in_array("NewspaperOrJournal", $this->getFormats())) {
            $result[] = 'periodicals_restriction_text';
        }
        $bases = array("facet_base_MZK03_znojmo", "facet_base_MZK03_rajhrad", "facet_base_MZK03_trebova",
            "facet_base_MZK03_dacice", "facet_base_MZK03_minorite", "facet_base_MZK03_broumov");
        foreach ($bases as $base) {
            if (in_array($base, $this->fields['base_txtF_mv'])) {
                $result[] = 'restriction_' . $base . '_text';
            }
        }
        return $result;
    }

    protected function translateHoldingStatus($status, $duedate_status)
    {
        $status = mb_substr($status, 0, 6, 'UTF-8');
        if ($duedate_status == 'On Shelf') {
            if ($status == 'Jen do' || $status == 'Studov') {
                return "present only";
            } else if ($status == 'Příruč') {
                return "reference";
            } else if ($status == 'Ve zpr') {
                return "";
            } else if ($status == 'Aktuál') {
                return "Newspapers and Journals - at the desk";
            }
        }
        if ($status == '0 po r') {
            return 'lost';
        } else if ($status == 'Nenale' || $duedate_status == 'Hledá ') {
            return 'lost - wanted';
        } else if ($status == 'Vyříze') {
            return 'lost by reader';
        }
        return $duedate_status;
    }

    public function getPublicationDates()
    {
        return isset($this->fields['publishDate_display']) ?
        $this->fields['publishDate_display'] : array();
    }

    public function getNativeLinks()
    {
        list($base, $sysno) = explode('-', $this->getUniqueID());
        $fullView = self::ALEPH_BASE_URL . "F?func=direct&doc_number=$sysno&local_base=$base&format=999";
        $holdings = self::ALEPH_BASE_URL . "F?func=item-global&doc_library=$base&doc_number=$sysno";
        return array(
            'native_link_full_view' => $fullView,
            'native_link_holdings'  => $holdings
        );
    }

    public function getCallNumber()
    {
        if (isset($this->fields['callnumber_str_mv'])) {
            return $this->fields['callnumber_str_mv'];
        } else {
            return array_unique($this->getFieldArray('910', array('b')));
        }
    }

    public function getItemLinks()
    {
        $itemLinks = $this->marcRecord->getFields('994');
        if (!is_array($itemLinks)) {
            return array();
        }
        $links = array();
        foreach ($itemLinks as $itemLink) {
            $base   = $itemLink->getSubfield('l')->getData();
            $sysno  = $itemLink->getSubfield('b')->getData();
            $label = $itemLink->getSubfield('n')->getData();
            $type   = $itemLink->getSubfield('a')->getData();
            $links[] = array(
                'id'    => $base . '-' . $sysno,
                'type'  => $type,
                'label' => $label,
            );
        }
        return $links;
    }

    public function getSubscribedYears()
    {
        return $this->getFirstFieldValue('910', array('r'));
    }

    public function getSubscribedVolumes()
    {
        return $this->getFirstFieldValue('910', array('s'));
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $retVal = [];

        // Which fields/subfields should we check for URLs?
        $fieldsToCheck = [
            '856' => ['y', 'z', '3'],   // Standard URL
            '555' => ['a']         // Cumulative index/finding aids
        ];

        foreach ($fieldsToCheck as $field => $subfields) {
            $urls = $this->getMarcRecord()->getFields($field);
            if ($urls) {
                foreach ($urls as $url) {
                    // Is there an address in the current field?
                    $address = $url->getSubfield('u');
                    if ($address) {
                        $address = $address->getData();
                        $subfield = null;

                        // Is there a description?  If not, just use the URL itself.
                        foreach ($subfields as $current) {
                            $desc = $url->getSubfield($current);
                            if ($desc) {
                                $subfield = $current;
                                break;
                            }
                        }
                        if ($desc) {
                            $desc = $desc->getData();
                            $part = $url->getSubfield('z');
                            if ($part && $subfield != 'z') {
                                $desc .= ' (' . $part->getData() . ')';
                            }
                        } else {
                            $desc = $address;
                        }

                        $retVal[] = ['url' => $address, 'desc' => $desc];
                    }
                }
            }
        }

        return $retVal;
    }


    public function getBibinfoForObalkyKnih()
    {
        $bibinfo = array(
            "authors" => array($this->getPrimaryAuthor()),
            "title" => $this->getTitle(),
            "ean" => $this->getEAN()
        );
        $isbn = $this->getCleanISBN();
        if (!empty($isbn)) {
            $bibinfo['isbn'] = $isbn;
        }
        $year = $this->getPublicationDates();
        if (!empty($year)) {
            $bibinfo['year'] = $year[0];
        }
        return $bibinfo;
    }

    public function getBibinfoForObalkyKnihV3()
    {
        $bibinfo = array();
        $isbn = $this->getCleanISBN();
        if (!empty($isbn)) {
            $bibinfo['isbn'] = $isbn;
        }
        $ean = $this->getEAN();
        if (!empty($ean)) {
            $bibinfo['ean'] = $ean;
        }
        $cnb = $this->getCNB();
        if (isset($cnb)) {
            $bibinfo['nbn'] = $cnb;
        } else {
            $prefix = 'BOA001';
            $bibinfo['nbn'] = $prefix . '-' . str_replace('-', '', $this->getUniqueID());
        }
        return $bibinfo;
    }

    public function getAvailabilityID() {
        if (isset($this->fields['availability_id_str'])) {
            return $this->fields['availability_id_str'];
        } else {
            return $this->getUniqueID();
        }
    }

    public function getEAN()
    {
        return (!empty($this->fields['ean_str_mv']) ? $this->fields['ean_str_mv'][0] : null);
    }

    protected function getCNB()
    {
        return isset($this->fields['nbn']) ? $this->fields['nbn'] : null;
    }

    public function getLocalId() {
        list($source, $localId) = explode('.', $this->getUniqueID());
        return $localId;
    }

   /**
    * uses setting from config.ini => External links
    * @return array  [] => [
    *          [institution] = institution,
    *          [url] = external link to catalogue,
    *          [display] => link to be possibly displayed]
    *          [id] => local identifier of record
    *
    */
    public function getExternalLinks() {

        list($ins, $id) = explode('.' , $this->getUniqueID());
        //FIXME temporary
        if (substr($ins, 0, 4) === "vnf_") $ins = substr($ins, 4);
	$linkBase = $this->recordConfig->ExternalLinks->$ins;

        if (empty($linkBase)) {
            return array(
                       array('institution' => $ins,
                             'url' => '',
                             'display' => '',
                             'id' => $this->getUniqueID()));
        }

        $finalID = $this->getExternalID();
        if (!isset($finalID)) {
            return array(
                       array('institution' => $ins,
                             'url' => '',
                             'display' => '',
                             'id' => $this->getUniqueID()));
        }

        $confEnd  = $ins . '_end';
        $linkEnd  = $this->recordConfig->ExternalLinks->$confEnd;
        if (!isset($linkEnd) ) $linkEnd = '';
        $externalLink =  $linkBase . $finalID . $linkEnd;
        return array(
                   array('institution' => $ins,
                         'url' => $externalLink,
                         'display' => $externalLink,
                         'id' => $id));
    }

    protected function getExternalID() {
        return $this->getLocalId();
    }

}