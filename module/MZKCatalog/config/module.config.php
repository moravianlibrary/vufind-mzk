<?php
namespace MZKCatalog\Module\Configuration;

$config = array(
    'vufind' => array(
        'plugin_managers' => array (
            'ils_driver' => array(
                'factories' => array(
                    'aleph' => 'MZKCatalog\ILS\Driver\Factory::getAleph',
                ), /* factories */
            ), /* ils_drivers */
            'recommend' => array(
                'factories' => array(
                    'specifiablefacets' => 'MZKCatalog\Recommend\Factory::getSpecifiableFacets',
                ), /* factories */
            ), /* recommend */
            'recorddriver' => array (
                'factories' => array(
                    'solrmzk'   => 'MZKCatalog\RecordDriver\Factory::getSolrMarc',
                    'solrmzk04' => 'MZKCatalog\RecordDriver\Factory::getSolrMarc',
                    'solrebsco' => 'MZKCatalog\RecordDriver\Factory::getEbscoSolrMarc',
                    'solrbookport' => 'MZKCatalog\RecordDriver\Factory::getBookportSolrMarc',
                    'solrdefault' => 'MZKCatalog\RecordDriver\Factory::getSolrMarc',
                ) /* factories */
            ), /* recorddriver */
            'recordtab' => array(
                'abstract_factories' => array('VuFind\RecordTab\PluginFactory'),
                'invokables' => array(
                    'holdingsils' => 'MZKCatalog\RecordTab\HoldingsILS',
                    'citation' => 'MZKCatalog\RecordTab\Citation',
                    'tagsandcomments' => 'MZKCatalog\RecordTab\TagsAndComments',
                ), /* invokables */
            ), /* recordtab */
            'db_table' => array(
                'invokables' => array(
                    'recordstatus' => 'MZKCatalog\Db\Table\RecordStatus',
                ),
            ),
        ), /* plugin_managers */
        'recorddriver_tabs' => array(
            'MZKCatalog\RecordDriver\SolrMarc' => array(
                'tabs' => array(
                    'Holdings' => 'HoldingsILS',
                    'Description' => 'Description',
                    'Citation' => 'Citation',
                    'TOC' => 'TOC',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'HierarchyTree' => 'HierarchyTree',
                    'Map' => 'Map',
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
            'MZKCatalog\RecordDriver\EbscoSolrMarc' => array(
                'tabs' => array(
                    'Description' => 'Description',
                    'TOC' => 'TOC',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
            'MZKCatalog\RecordDriver\BookportSolrMarc' => array(
                'tabs' => array(
                    'Description' => 'Description',
                    'TOC' => 'TOC',
                    'UserComments' => 'UserComments',
                    'Reviews' => 'Reviews',
                    'Excerpt' => 'Excerpt',
                    'Details' => 'StaffViewMARC',
                ),
                'defaultTab' => null,
            ),
        ) /* recorddriver_tabs */
    ), /* vufind */
    'service_manager' => array(
        'factories' => array(
            'MZKCatalog\AlephBrowse\Connector' => 'MZKCatalog\AlephBrowse\Factory::getAlephBrowseConnector',
            // 'VuFind\Translator' => 'MZKCatalog\Service\Factory::getTranslator',
            'VuFind\ILSHoldLogic' => 'MZKCatalog\ILS\Logic\Factory::getFlatHolds',
        ),
    ),
    'controllers' => array(
        'factories' => array(
            'record' => 'MZKCatalog\Controller\Factory::getRecordController',
            'alephbrowse' => 'MZKCatalog\Controller\Factory::getAlephBrowseController',
        ),
        'invokables' => array(
            'search'     => 'MZKCatalog\Controller\SearchController',
            'ajax'       => 'MZKCatalog\Controller\AjaxController',
            'myresearch' => 'MZKCatalog\Controller\MyResearchController',
        ),
    ),
    'controller_plugins' => array(
        'factories' => array(
            'shortLoanRequests'   => 'MZKCatalog\Controller\Plugin\Factory::getShortLoanRequests',
        ),
    ),
);

$staticRoutes = array(
    'AlephBrowse/Home',
    'Search/Conspectus', 'Search/MostSearched', 'Search/NewAcquisitions',
    'MyResearch/CheckedOutHistory', 'MyResearch/ShortLoans',
    'MyResearch/FavoritesImport', 'MyResearch/ProfileChange',
);

foreach ($staticRoutes as $route) {
    list($controller, $action) = explode('/', $route);
    $routeName = str_replace('/', '-', strtolower($route));
    $config['router']['routes'][$routeName] = array(
        'type' => 'Zend\Mvc\Router\Http\Literal',
        'options' => array(
            'route'    => '/' . $route,
            'defaults' => array(
                'controller' => $controller,
                'action'     => (! empty($action)) ? $action : 'default',
            )
        )
    );
}

$nonTabRecordActions = array('DigiRequest','ShortLoan');

foreach ($nonTabRecordActions as $action) {
    $config['router']['routes']['record' . '-' . strtolower($action)] = array(
        'type'    => 'Zend\Mvc\Router\Http\Segment',
        'options' => array(
            'route'    => '/' . 'Record' . '/[:id]/' . $action,
            'constraints' => array(
                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
            ),
            'defaults' => array(
                'controller' => 'Record',
                'action'     => $action,
            )
        )
    );
}

return $config;
