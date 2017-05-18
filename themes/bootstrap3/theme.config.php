<?php
return array(
    'extends' => 'root',
    'css' => array(
        //'vendor/bootstrap.min.css',
        //'vendor/bootstrap-accessibility.css',
        //'bootstrap-custom.css',
        'compiled.css',
        'vendor/font-awesome.min.css',
        'vendor/bootstrap-slider.css',
        'print.css:print',
        'ol.css'
    ),
    'js' => array(
        'vendor/base64.js:lt IE 10', // btoa polyfill
        'vendor/jquery.min.js',
        'vendor/bootstrap.min.js',
        //'vendor/bootstrap-accessibility.min.js',
        //'vendor/bootlint.min.js',
        'autocomplete.js',
        'vendor/validator.min.js',
        'vendor/rc4.js',
        'common.js',
        'lightbox.js',
        'obalkyknih.js'
    ),
    'less' => array(
        'active' => false,
        'compiled.less'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'flashmessages' => 'VuFind\View\Helper\Bootstrap3\Factory::getFlashmessages',
            'record' => function ($sm) {
                return new \MZKCommon\View\Helper\MZKCommon\Record(
                    $sm->getServiceLocator()->get('VuFind\Config')->get('config')
                );
            },
            'layoutclass' => 'MZKCatalog\View\Helper\MzkTheme\Factory::getLayoutClass',
            'mzkhelper'   => 'MZKCatalog\View\Helper\MzkTheme\Factory::getMzkHelper',
            'obalkyknih' => 'ObalkyKnihV3\View\Helper\ObalkyKnih\Factory::getObalkyKnih',
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap3\VuDL',
        )
    )
);
