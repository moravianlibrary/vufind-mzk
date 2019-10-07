<?php
/**
 * MyResearch Controller
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace MZKCatalog\Controller;

use VuFind\Controller\RecordController as RecordControllerBase;

/**
 * Redirects the user to the appropriate default VuFind action.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends RecordControllerBase
{

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        // Call standard record controller initialization:
        parent::__construct($config);
    
        if (!isset($config->{'DigiRequest'}->to)) {
            
        }
        
        if (!isset($config->{'DigiRequest'}->from)) {
            
        }
        
        // Load default tab setting:
        $this->digiRequestFrom = $config->Site->email;
        $this->digiRequestTo = explode(',', $config->{'DigiRequest'}->to);
        $this->digiRequestSubject = $config->{'DigiRequest'}->subject;
        
    }

    /**
     * Add a tag
     *
     * @return mixed
     */
    public function addtagAction()
    {
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }

        // Obtain the current record object:
        $driver = $this->loadRecord();

        // Save tags, if any:
        if ($this->params()->fromPost('submit')) {
            $tags = $this->params()->fromPost('tag');
            $tagParser = $this->getServiceLocator()->get('VuFind\Tags');
            $driver->addTags($user, $tagParser->parse($tags));
            return $this->redirectToRecord('', 'TagsAndComments');
        }

        // Display the "add tag" form:
        $view = $this->createViewModel();
        $view->setTemplate('record/addtag');
        return $view;
    }

    public function shortLoanAction()
    {
        $driver = $this->loadRecord();

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction("Holds", $driver->getUniqueID());
        if (!$checkHolds) {
            return $this->forwardTo('Record', 'Home');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        $showLinks = false;

        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeHold'))) {
            $slots = $this->params()->fromPost('slot');
            if (!$slots) {
                $this->flashMessenger()->setNamespace('error')->addMessage('short_loan_no_slot_selected_error');
            } else {
                $numOfFailures = 0;
                sort($slots);
                foreach ($slots as $slot) {
                    $details = array();
                    $details['patron'] = $patron;
                    $details['id'] = $driver->getUniqueID();
                    $details['item_id'] = $this->params()->fromQuery('item_id');
                    $details['slot'] = $slot;
                    try {
                        $result = $catalog->placeShortLoanRequest($details);
                        if (!$result['success']) {
                            $numOfFailures++;
                        }
                    } catch (\Exception $ex) {
                        $numOfFailures++;
                    }
                }
                if ($numOfFailures == count($slots)) { // All requests failed
                    $this->flashMessenger()->setNamespace('error')->addMessage('short_loan_request_error_text');
                    $showLinks = true;
                } else if ($numOfFailures > 0) {
                    $this->flashMessenger()->setNamespace('error')->addMessage('short_loan_request_partial_error_text');
                    $showLinks = true;
                } else {
                    $this->flashMessenger()->setNamespace('success')->addMessage('short_loan_ok_text');
                    return $this->redirectToRecord();
                }
            }
        }

        $shortLoanInfo = $catalog->getHoldingInfoForItem($patron['id'],
            $driver->getUniqueID(), $this->params()->fromQuery('item_id'));

        $slotsByDate = array();
        foreach ($shortLoanInfo['slots'] as $id => $slot) {
            $start_date = $slot['start_date'];
            $start_time = $slot['start_time'];
            $slotsByDate[$start_date][$start_time] = $slot;
            $slotsByDate[$start_date][$start_time]['id'] = $id;
            $slotsByDate[$start_date][$start_time]['available'] = $slot['available'];
        }

        static $positions = array(
            '1000' => 1,
            '1200' => 2,
            '1400' => 3,
            '1600' => 4,
            '1800' => 5,
            '2000' => 6
        );

        $results = array();
        foreach ($slotsByDate as $date => $slotsInDate) {
            $result = array_fill(0, 7, array('available' => false));
            $firstSlot = key($slotsInDate);
            $position = 0;
            foreach ($positions as $start => $index) {
                if ($firstSlot >= $start) {
                    $position = $index;
                }
            }
            foreach ($slotsInDate as $start_time => $slot) {
                $start_time = $slot['start_time'];
                $slot['start_time'] = substr($start_time, 0, 2) . ':' . substr($start_time, 2, 2);
                $end_time = $slot['end_time'];
                $slot['end_time'] = substr($end_time, 0, 2) . ':' . substr($end_time, 2, 2);
                $result[$position] = $slot;
                $position++;
            }
            $date = date_parse_from_format('Ymd', $date);
            $date =  $date['day'] . '. ' . $date['month'] . '.';
            $results[$date] = $result;
        }

        $view = $this->createViewModel(
            array(
                'showLinks'  => $showLinks,
                'slots'      => $results,
                'callnumber' => $shortLoanInfo['callnumber']
            )
        );
        $view->setTemplate('record/shortloan');
        return $view;
    }


    /**
     * Action for dealing with holds.
     *
     * @return mixed
     */
    public function holdAction()
    {
        $driver = $this->loadRecord();

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction("Holds", $driver->getUniqueID());
        if (!$checkHolds) {
            return $this->forwardTo('Record', 'Home');
        }

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        if (!$catalog->checkRequestIsValid(
                $driver->getUniqueID(), $gatheredDetails, $patron
        )) {
            return $this->blockedholdAction();
        }

        // Send various values to the view so we can build the form:

        $details = $catalog->getHoldingInfoForItem($patron['id'], $gatheredDetails['id'],
                $gatheredDetails['item_id']);
        $pickup = array();
        foreach ($details['pickup-locations'] as $key => $value) {
            $pickup[] = array(
                "locationID" => $key, "locationDisplay" => $value
            );
        }

        $order = $details['order'];
        $dueDate = $details['due-date'];
        $queued = $dueDate != null;
        $status = $details['status'];

        $requestGroups = $catalog->checkCapability('getRequestGroups')
        ? $catalog->getRequestGroups($driver->getUniqueID(), $patron)
        : array();
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
        ? explode(":", $checkHolds['extraHoldFields']) : array();

        // Process form submissions if necessary:
        if (!is_null($this->params()->fromPost('placeHold'))) {
            // If the form contained a pickup location or request group, make sure
            // they are valid:
            $valid = $this->holds()->validateRequestGroupInput(
                    $gatheredDetails, $extraHoldFields, $requestGroups
            );
            if (!$valid) {
                $this->flashMessenger()->setNamespace('error')
                ->addMessage('hold_invalid_request_group');
            } elseif (!$this->holds()->validatePickUpInput(
                    $gatheredDetails['pickUpLocation'], $extraHoldFields, $pickup
            )) {
                $this->flashMessenger()->setNamespace('error')
                ->addMessage('hold_invalid_pickup');
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.
    
                // Add Patron Data to Submitted Data
                $holdDetails = $gatheredDetails + array('patron' => $patron);
    
                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);
    
                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $this->flashMessenger()->setNamespace('info')
                    ->addMessage('hold_place_success');
                    if ($this->inLightbox()) {
                        return false;
                    }
                    return $this->redirect()->toRoute('myresearch-holds');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->setNamespace('error')
                        ->addMessage($results['status']);
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()->setNamespace('error')
                        ->addMessage($results['sysMessage']);
                    }
                }
            }
        }

        // Find and format the default required date:
        if (!$queued) {
            $defaultRequired = $this->holds()->getDefaultRequiredDate(
                    $checkHolds, $catalog, $patron, $gatheredDetails
                );
            $defaultRequired = $this->getServiceLocator()->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", $defaultRequired);
        } else {
            $defaultRequired = null;
        }

        try {
            $defaultPickup
            = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }
        try {
            $defaultRequestGroup = empty($requestGroups)
            ? false
            : $catalog->getDefaultRequestGroup($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultRequestGroup = false;
        }

        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
        && !empty($requestGroups)
        && (empty($gatheredDetails['level'])
                || $gatheredDetails['level'] != 'copy');

        return $this->createViewModel(
                array(
                    'gatheredDetails' => $gatheredDetails,
                    'pickup' => $pickup,
                    'dueDate' => $dueDate,
                    'defaultPickup' => $defaultPickup,
                    'homeLibrary' => $this->getUser()->home_library,
                    'extraHoldFields' => $extraHoldFields,
                    'defaultRequiredDate' => $defaultRequired,
                    'requestGroups' => $requestGroups,
                    'defaultRequestGroup' => $defaultRequestGroup,
                    'requestGroupNeeded' => $requestGroupNeeded,
                    'queued' => $queued,
                    'order' => $order,
                    'reservation' => ($order > 1 || $dueDate),
                    'processing' => $status == 'ZP',
                    'helpText' => isset($checkHolds['helpText'])
                    ? $checkHolds['helpText'] : null
                )
        );
    }

    public function digiRequestAction()
    {
        // Force login:
        if (!($user = $this->getUser())) {
            return $this->forceLogin(null);
        }

        // Process form submission:
        if ($this->params()->fromPost('submit')) {
            $this->processDigiRequest();
        }
        
        // use user email as default value
        $email = $user->email;
        // Retrieve the record driver:
        $driver = $this->loadRecord();
        
        $view = $this->createViewModel(
            array(
                'email'  => $email,
                'driver' => $driver
            )
        );
        $view->setTemplate('record/digitalization-request');
        return $view;
    }

    /**
     * ProcessSave -- store the results of the Save action.
     *
     * @return mixed
     */
    protected function processDigiRequest()
    {
        $post = $this->getRequest()->getPost()->toArray();
        $email = $post['email'];
        $reason = $post['reason'];
        $driver = $this->loadRecord();
        $params = array(
            'email'  => $email,
            'reason' => $reason,
            'driver' => $driver
        );
        $text = $this->getViewRenderer()->render('Email/digitalization-request.phtml', $params);
        $mailer = $this->getServiceLocator()->get('VuFind\Mailer');
        foreach ($this->digiRequestTo as $recipient) {
            $mailer->send(
                $recipient,
                $this->digiRequestFrom,
                $this->digiRequestSubject,
                $text
            );
        }
        return $this->redirectToRecord();
    }

}
