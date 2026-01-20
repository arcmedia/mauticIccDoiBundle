<?php

use MauticPlugin\IccDoiBundle\Controller\EmailTypeController;

return [
    'name' => 'Icc DOI Bundle',
    'description' => 'Insignio DOI process bundle',
    'author' => 'Arcmedia',
    'version' => '1.0.0',
    'routes' => [
        'public' => [
            'icc_doi_emailtype' => [
                'path' => '/iccdoi/emailtype',
                'controller' => EmailTypeController::class . '::setAction',
            ],
        ],
    ],
    'menu' => [],
    'parameters' => [],
];
