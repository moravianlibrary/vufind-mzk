<?php
/**
 * Admin Controller
 *
 * PHP version 5
 *
 * Copyright (C) Moravian Library 2016.
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
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
namespace CPK\Controller;

use MZKCommon\Controller\ExceptionsTrait;

/**
 * Class controls VuFind administration.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Martin Kravec <martin.kravec@mzk.cz>
 * @license  http://opensource.org/licenses/gpl-3.0.php GNU General Public License
 */
class AdminController extends \VuFind\Controller\AbstractBase
{
    use ExceptionsTrait;
    
    /**
     * Admin home.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        // Log in first!
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }

        if (empty($user['major'])) {
            return $this->forceLogin('Wrong permissions');
        }
        // Logged In successfull
        
        $this->layout()->searchbox = false;
        return $this->createViewModel(['user' => $user]);
    }
    
    public function portalPagesAction()
    {
        // Log in first!
        if (! $user = $this->getAuthManager()->isLoggedIn()) {
            $this->flashExceptions($this->flashMessenger());
            return $this->forceLogin();
        }
    
        if (empty($user['major'])) {
            return $this->forceLogin('Wrong permissions');
        }
        // Logged In successfull
        
        $viewModel = $this->createViewModel();
        $viewModel->setVariable('user', $user);
        
        $portalPagesTable = $this->getTable("portalpages");
        
        $routeParam = $this->params()->fromRoute('page');
        if ($routeParam == 'Edit') { // is edit in route?
            $pageId = (int) $this->params()->fromQuery('pageId');
            $page = $portalPagesTable->getPageById($pageId);
            $viewModel->setVariable('page', $page);
            
            $positions = ['left', 'middle', 'right'];
            $viewModel->setVariable('positions', $positions);
            
            $placements = ['footer'];
            $viewModel->setVariable('placements', $placements);
            
            $viewModel->setTemplate('admin/edit-portal-page');
        } else if ($routeParam == 'Save') {
            $post = $this->params()->fromPost();
            $portalPagesTable->save($post);
            return $this->forwardTo('Admin', 'PortalPages');
        } else { // normal view
    	    $allPages = $portalPagesTable->getAllPages('*', false);
    	    $viewModel->setVariable('pages', $allPages);
        }
        
        $this->layout()->searchbox = false;
        return $viewModel;
    }
}
