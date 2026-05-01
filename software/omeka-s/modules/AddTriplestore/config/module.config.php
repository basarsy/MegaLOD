<?php
return [
    'service_manager' => [
        'factories' => [
            \AddTriplestore\Service\MegalodConfig::class => \AddTriplestore\Service\MegalodConfigFactory::class,
            \AddTriplestore\Service\GraphDbCredentialService::class => \AddTriplestore\Service\GraphDbCredentialServiceFactory::class,
            \AddTriplestore\Service\OmekaApiCredentialService::class => \AddTriplestore\Service\OmekaApiCredentialServiceFactory::class,
            \AddTriplestore\Service\ExcavationItemSetContextService::class => \AddTriplestore\Service\ExcavationItemSetContextServiceFactory::class,
            \AddTriplestore\Service\GraphDbHttpService::class => \AddTriplestore\Service\GraphDbHttpServiceFactory::class,
            \AddTriplestore\Service\Ttl\TtlUriHelper::class => \AddTriplestore\Service\Ttl\TtlUriHelperFactory::class,
            \AddTriplestore\Service\Ttl\ExcavationTtlBuilder::class => \AddTriplestore\Service\Ttl\ExcavationTtlBuilderFactory::class,
            \AddTriplestore\Service\Ttl\ArrowheadTtlBuilder::class => \AddTriplestore\Service\Ttl\ArrowheadTtlBuilderFactory::class,
            \AddTriplestore\Service\Ttl\TtlUriNormalizer::class => \AddTriplestore\Service\Ttl\TtlUriNormalizerFactory::class,
            \AddTriplestore\Service\Ttl\XmlToTtlPipeline::class => \AddTriplestore\Service\Ttl\XmlToTtlPipelineFactory::class,
            \AddTriplestore\Service\Ttl\TtlContentInspectionService::class => \AddTriplestore\Service\Ttl\TtlContentInspectionServiceFactory::class,
            \AddTriplestore\Service\Ttl\UploadedFileToTtlConverter::class => \AddTriplestore\Service\Ttl\UploadedFileToTtlConverterFactory::class,
            \AddTriplestore\Service\Ingestion\OmekaResourceLookupService::class => \AddTriplestore\Service\Ingestion\OmekaResourceLookupServiceFactory::class,
            \AddTriplestore\Service\Ingestion\CollectingFormToArrowheadMapper::class => \AddTriplestore\Service\Ingestion\CollectingFormToArrowheadMapperFactory::class,
            \AddTriplestore\Service\Ingestion\OmekaIngestionService::class => \AddTriplestore\Service\Ingestion\OmekaIngestionServiceFactory::class,
            \AddTriplestore\Service\Ingestion\OmekaRestSubmissionService::class => \AddTriplestore\Service\Ingestion\OmekaRestSubmissionServiceFactory::class,
            \AddTriplestore\Service\Encounter\EncounterEventService::class => \AddTriplestore\Service\Encounter\EncounterEventServiceFactory::class,
            \AddTriplestore\Service\OmekaSiteResourceService::class => \AddTriplestore\Service\OmekaSiteResourceServiceFactory::class,
            \AddTriplestore\Service\SiteUserAuthSupport::class => \AddTriplestore\Service\SiteUserAuthSupportFactory::class,
            \AddTriplestore\Service\SiteMetadataOptionsService::class => \AddTriplestore\Service\SiteMetadataOptionsServiceFactory::class,
            \AddTriplestore\Service\Ttl\VocabularyLabelService::class => \AddTriplestore\Service\Ttl\VocabularyLabelServiceFactory::class,
            \AddTriplestore\Service\Ttl\TtlPresentationService::class => \AddTriplestore\Service\Ttl\TtlPresentationServiceFactory::class,
            \AddTriplestore\Service\Ttl\ArrowheadCanonicalTtlExporter::class => \AddTriplestore\Service\Ttl\ArrowheadCanonicalTtlExporterFactory::class,
            \AddTriplestore\Service\Ingestion\CollectingFormToExcavationDataMapper::class => \AddTriplestore\Service\Ingestion\CollectingFormToExcavationDataMapperFactory::class,
            \AddTriplestore\Service\Upload\TtlUploadOrchestrationService::class => \AddTriplestore\Service\Upload\TtlUploadOrchestrationServiceFactory::class,
        ],
    ],
    'controllers' => [
        'factories' => [
            'AddTriplestore\Controller\Site\Index' => 'AddTriplestore\Controller\Site\IndexControllerFactory',
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'add-triplestore' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/add-triplestore[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'AddTriplestore\Controller\Site',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'upload' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/upload',
                                    'defaults' => [
                                        'controller' => 'AddTriplestore\Controller\Site\Index',
                                        'action' => 'upload',
                                    ],
                                ],
                            ],
                            'process-collecting' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/process-collecting',
                                    'defaults' => [
                                        'controller' => 'AddTriplestore\Controller\Site\Index',
                                        'action' => 'processCollectingForm',
                                    ],
                                ],
                            ],
                            'search' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/search',
                                    'defaults' => [
                                        'controller' => 'AddTriplestore\Controller\Site\Index',
                                        'action' => 'search',
                                    ],
                                ],
                            ],
                            'login' => [
                            'type' => 'Literal',
                            'options' => [
                                'route' => '/login',
                                'defaults' => [
                                    'controller' => 'AddTriplestore\Controller\Site\Index',
                                    'action' => 'login',
                                ],
                            ],
                        ],
                        'signup' => [
                            'type' => 'Literal',
                            'options' => [
                                'route' => '/signup',
                                'defaults' => [
                                    'controller' => 'AddTriplestore\Controller\Site\Index',
                                    'action' => 'signup',
                                ],
                            ],
                        ],
                        'logout' => [
                            'type' => 'Literal',
                            'options' => [
                                'route' => '/logout',
                                'defaults' => [
                                    'controller' => 'AddTriplestore\Controller\Site\Index',
                                    'action' => 'logout',
                                ],
                            ],
                        ],
                        'sparql' => [
                            'type' => 'Literal',
                            'options' => [
                                'route' => '/sparql',
                                'defaults' => [
                                    'controller' => 'AddTriplestore\Controller\Site\Index',
                                    'action' => 'sparql',
                                ],
                            ],
                        ],
                            'view-details' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/view-details',
                                    'defaults' => [
                                        'controller' => 'AddTriplestore\Controller\Site\Index',
                                        'action' => 'viewDetails',
                                    ],
                                ],
                            ],
                            'download-ttl' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/download-ttl',
                                    'defaults' => [
                                        'controller' => 'AddTriplestore\Controller\Site\Index',
                                        'action' => 'downloadTtl',
                                    ],
                                ],
                            ],
                            'about-us' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/about-us',
                                    'defaults' => [
                                        'controller' => 'AddTriplestore\Controller\Site\Index',
                                        'action' => 'aboutUs',
                                    ],
                                ],
                            ],
                            'dashboard' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/dashboard',
                                    'defaults' => [
                                        'action' => 'dashboard',
                                    ],
                                ],
                            ],
                            'my-data' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/my-data',
                                    'defaults' => [
                                        'action' => 'myData',
                                    ],
                                ],
                            ],
                            'download-template' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/download-template',
                                    'defaults' => [
                                        'controller' => 'AddTriplestore\Controller\Site\Index',
                                        'action' => 'downloadTemplate',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'asset_manager' => [
        'resolver_configs' => [
            'paths' => [
                'AddTriplestore' => __DIR__ . '/../asset', 
            ],
        ],
    ],
    'navigation' => [
    'site' => [
        [
            'label' => 'Archaeological Data',
            'route' => 'site/add-triplestore',
            'params' => [
                'site-slug' => '__SITE_SLUG__' 
            ],
            'pages' => [
                [
                    'label' => 'Search',
                    'route' => 'site/add-triplestore/search',
                    'params' => [
                        'site-slug' => '__SITE_SLUG__' 
                    ],
                ],
                [
                    'label' => 'Add Excavation',
                    'route' => 'site/add-triplestore/upload',
                    'params' => [
                        'site-slug' => '__SITE_SLUG__', 
                        'query' => [
                            'upload_type' => 'excavation'
                        ]
                    ],
                ],
            ],
        ],
    ],
],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => __DIR__ . '/../language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
];