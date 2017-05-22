<?php
/**
 * Table Definition for statistics
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace MZKCatalog\Db\Table;

use VuFind\Db\Table\Gateway;
use Zend\Db\Sql\Expression;

/**
 * Table Definition for statistics
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Vaclav Rosecky <xrosecky@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordStatus extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('record_status', 'MZKCatalog\Db\Row\RecordStatus');
    }

    /**
     * Get status by source for given ids
     *
     * @param int    $source   source
     * @param array  $ids      id of bib records
     *
     */
    public function getByIds($source, $ids)
    {
        $callback = function ($select) use ($source, $ids) {
            $select->where->equalTo('source', $source);
            $select->where->in('record_id', $ids);
        };
        return $this->select($callback)->toArray();
    }
    
}
