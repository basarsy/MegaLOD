<?php

namespace AddTriplestore\Controller\Site;

require 'vendor/autoload.php';

use AddTriplestore\Service\Encounter\EncounterEventService;
use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\Ingestion\CollectingFormToArrowheadMapper;
use AddTriplestore\Service\Ingestion\CollectingFormToExcavationDataMapper;
use AddTriplestore\Service\Ingestion\OmekaIngestionService;
use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\Ingestion\OmekaRestSubmissionService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\OmekaSiteResourceService;
use AddTriplestore\Service\SiteMetadataOptionsService;
use AddTriplestore\Service\SiteUserAuthSupport;
use AddTriplestore\Service\Ttl\ArrowheadCanonicalTtlExporter;
use AddTriplestore\Service\Ttl\ArrowheadTtlBuilder;
use AddTriplestore\Service\Ttl\ExcavationTtlBuilder;
use AddTriplestore\Service\Ttl\TtlContentInspectionService;
use AddTriplestore\Service\Ttl\TtlPresentationService;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use AddTriplestore\Service\Ttl\TtlUriNormalizer;
use AddTriplestore\Service\Ttl\UploadedFileToTtlConverter;
use AddTriplestore\Service\Ttl\VocabularyLabelService;
use AddTriplestore\Service\Ttl\XmlToTtlPipeline;
use AddTriplestore\Service\Upload\TtlUploadOrchestrationService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Http\Client;
use EasyRdf\Graph;
use Laminas\Form\FormInterface;
use Laminas\Router\RouteStackInterface;
use Laminas\Session\Container;
use Laminas\Validator\Csrf as CsrfValidator;

class IndexController extends AbstractActionController
{
    private $graphdbEndpoint;
    private $graphdbQueryEndpoint;
    private $baseDataGraphUri;
    private $localBaseUri;
    private $graphdbWorkbenchUrl;
    private $router;
    private $httpClient;

    /** @var GraphDbHttpService */
    private $graphDbHttpService;

    /** @var TtlUriHelper */
    private $ttlUriHelper;

    /** @var ExcavationTtlBuilder */
    private $excavationTtlBuilder;

    /** @var ArrowheadTtlBuilder */
    private $arrowheadTtlBuilder;

    /** @var TtlUriNormalizer */
    private $ttlUriNormalizer;

    /** @var XmlToTtlPipeline */
    private $xmlToTtlPipeline;

    /** @var OmekaResourceLookupService */
    private $omekaResourceLookupService;

    /** @var OmekaIngestionService */
    private $omekaIngestionService;

    /** @var OmekaRestSubmissionService */
    private $omekaRestSubmissionService;

    /** @var ExcavationItemSetContextService */
    private $excavationItemSetContextService;

    /** @var CollectingFormToArrowheadMapper */
    private $collectingFormToArrowheadMapper;

    /** @var TtlContentInspectionService */
    private $ttlContentInspectionService;

    /** @var UploadedFileToTtlConverter */
    private $uploadedFileToTtlConverter;

    /** @var EncounterEventService */
    private $encounterEventService;

    /** @var SiteMetadataOptionsService */
    private $siteMetadataOptionsService;

    /** @var VocabularyLabelService */
    private $vocabularyLabelService;

    /** @var TtlPresentationService */
    private $ttlPresentationService;

    /** @var ArrowheadCanonicalTtlExporter */
    private $arrowheadCanonicalTtlExporter;

    /** @var CollectingFormToExcavationDataMapper */
    private $collectingFormToExcavationDataMapper;

    /** @var OmekaSiteResourceService */
    private $omekaSiteResourceService;

    /** @var SiteUserAuthSupport */
    private $siteUserAuthSupport;

    /** @var TtlUploadOrchestrationService */
    private $ttlUploadOrchestrationService;

    private $uploadedFiles = null;

    private $excavationData = null;

    private $excavationIdentifier = "0";
    
    private $currentProcessingItemSetId = null;

    private $csrfValidator = null;
        

    public function __construct(
        RouteStackInterface $router,
        Client $httpClient,
        MegalodConfig $megalodConfig,
        GraphDbHttpService $graphDbHttpService,
        TtlUriHelper $ttlUriHelper,
        ExcavationTtlBuilder $excavationTtlBuilder,
        ArrowheadTtlBuilder $arrowheadTtlBuilder,
        TtlUriNormalizer $ttlUriNormalizer,
        XmlToTtlPipeline $xmlToTtlPipeline,
        OmekaResourceLookupService $omekaResourceLookupService,
        OmekaIngestionService $omekaIngestionService,
        OmekaRestSubmissionService $omekaRestSubmissionService,
        ExcavationItemSetContextService $excavationItemSetContextService,
        CollectingFormToArrowheadMapper $collectingFormToArrowheadMapper,
        TtlContentInspectionService $ttlContentInspectionService,
        UploadedFileToTtlConverter $uploadedFileToTtlConverter,
        EncounterEventService $encounterEventService,
        SiteMetadataOptionsService $siteMetadataOptionsService,
        VocabularyLabelService $vocabularyLabelService,
        TtlPresentationService $ttlPresentationService,
        ArrowheadCanonicalTtlExporter $arrowheadCanonicalTtlExporter,
        CollectingFormToExcavationDataMapper $collectingFormToExcavationDataMapper,
        OmekaSiteResourceService $omekaSiteResourceService,
        SiteUserAuthSupport $siteUserAuthSupport,
        TtlUploadOrchestrationService $ttlUploadOrchestrationService
    ) {
        $this->router = $router;
        $this->httpClient = $httpClient;
        $this->graphDbHttpService = $graphDbHttpService;
        $this->ttlUriHelper = $ttlUriHelper;
        $this->excavationTtlBuilder = $excavationTtlBuilder;
        $this->arrowheadTtlBuilder = $arrowheadTtlBuilder;
        $this->ttlUriNormalizer = $ttlUriNormalizer;
        $this->xmlToTtlPipeline = $xmlToTtlPipeline;
        $this->omekaResourceLookupService = $omekaResourceLookupService;
        $this->omekaIngestionService = $omekaIngestionService;
        $this->omekaRestSubmissionService = $omekaRestSubmissionService;
        $this->excavationItemSetContextService = $excavationItemSetContextService;
        $this->collectingFormToArrowheadMapper = $collectingFormToArrowheadMapper;
        $this->ttlContentInspectionService = $ttlContentInspectionService;
        $this->uploadedFileToTtlConverter = $uploadedFileToTtlConverter;
        $this->encounterEventService = $encounterEventService;
        $this->siteMetadataOptionsService = $siteMetadataOptionsService;
        $this->vocabularyLabelService = $vocabularyLabelService;
        $this->ttlPresentationService = $ttlPresentationService;
        $this->arrowheadCanonicalTtlExporter = $arrowheadCanonicalTtlExporter;
        $this->collectingFormToExcavationDataMapper = $collectingFormToExcavationDataMapper;
        $this->omekaSiteResourceService = $omekaSiteResourceService;
        $this->siteUserAuthSupport = $siteUserAuthSupport;
        $this->ttlUploadOrchestrationService = $ttlUploadOrchestrationService;

        $this->graphdbEndpoint = $megalodConfig->getGraphdbRdfGraphsServiceUrl();
        $this->graphdbQueryEndpoint = $megalodConfig->getGraphdbQueryEndpoint();
        $this->baseDataGraphUri = $megalodConfig->getMegalodPublicBaseUri();
        $this->localBaseUri = $megalodConfig->getMegalodLocalBaseUri();
        $this->graphdbWorkbenchUrl = $megalodConfig->getGraphdbWorkbenchUrl();
    }



    // ================== ACTION METHODS ==================

    // MAIN ACTION INDEX ACTION

    /**
     * Site page default action
     *
     * Renders the main page of the AddTriplestore module in the site context.
     * Checks if the user is currently logged in and passes this status to the view along with the current site information.
     * @return \Laminas\View\Model\ViewModel The view model with site and login status
     */
    public function indexAction()
    {
        $site = $this->currentSite();
        $isLoggedIn = (bool) $this->identity();

        return new ViewModel([
            'site' => $site,
            'isLoggedIn' => $isLoggedIn,
            'csrfToken' => $isLoggedIn ? $this->generateCsrfToken() : '',
        ]);
    }

    // ================== AUTH ACTIONS ==================

    /**
     * Log out a user from the site
     *
     * Clears the user identity, site-specific user session data,
     * and destroys the session. Then redirects back to the site homepage
     *
     * @return \Laminas\Http\Response
     */
    public function logoutAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute('site', ['site-slug' => $this->currentSite()->slug()]);
        }

        if (!$this->validateCsrfToken()) {
            return $this->redirect()->toRoute('site', ['site-slug' => $this->currentSite()->slug()]);
        }

        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();

        $session = new Container('site_user');
        $session->getManager()->getStorage()->clear();

        $sessionManager = Container::getDefaultManager();
        $sessionManager->destroy();

        $this->messenger()->addSuccess('Successfully logged out');
        return $this->redirect()->toRoute('site', ['site-slug' => $this->currentSite()->slug()]);
    }


    /**
     * Handle user signup for the site.
     * 
     * This action allows visitors to create a site-only user account.
     * If the user is already logged in, they will be redirected to the site's homepage.
     *
     * @return \Laminas\View\Model\ViewModel|\Laminas\Http\Response
     * Returns either a ViewModel with the signup form for GET requests or invalid POST submissions, or a redirect response on successful signup or if user is already logged in.
     */
    public function signupAction()
    {
        if ($this->identity()) {
            return $this->redirect()->toRoute('site', ['site-slug' => $this->currentSite()->slug()]);
        }

        $form = $this->getSignupForm();
        $view = new ViewModel([
            'form' => $form,
            'site' => $this->currentSite(),
            'csrfToken' => $this->generateCsrfToken(),
        ]);
        $view->setTemplate('add-triplestore/site/index/signup');

        if ($this->getRequest()->isPost()) {
            if (!$this->validateCsrfToken()) {
                return $view;
            }
            $data = $this->params()->fromPost();
            $form->setData($data);
            
            if ($form->isValid()) {
                $validatedData = $form->getData();
                
                // Check if passwords match
                if ($validatedData['password'] !== $validatedData['confirm_password']) {
                    $this->messenger()->addError('Passwords do not match');
                    return $view;
                }
                
                try {
                    $result = $this->siteUserAuthSupport->createSiteOnlyUser($validatedData, (int) $this->currentSite()->id());
                    
                    if ($result['success']) {
                        $this->messenger()->addSuccess('Account created successfully! You can now log in.');
                        return $this->redirect()->toRoute('site/add-triplestore/login', ['site-slug' => $this->currentSite()->slug()]);
                    } else {
                        $this->messenger()->addError($result['error']);
                        return $view;
                    }
                    
                } catch (\Exception $e) {

                    $this->messenger()->addError('Error creating account: ' . $e->getMessage());
                    return $view;
                }
            } else {
                $this->messenger()->addError('Please correct the errors in the form');
            }
        }
        
        return $view;
    }



    /**
     * Handle user login for the Add Triplestore module.
     *
     * Processes login requests for admin and guest users. Redirects logged-in users
     * to the appropriate dashboard based on their role.
     *
     * @return \Laminas\View\Model\ViewModel|\Laminas\Http\Response
     */
    public function loginAction()
    {
        // If already logged in, redirect to main page
        if ($this->identity()) {
            $user = $this->identity();
            
            // Check if user is a guest/site-only user
            if ($user->getRole() === 'guest') {
                // return go to custom dashboard
                return $this->redirect()->toRoute('site/add-triplestore/dashboard', [
                    'site-slug' => $this->currentSite()->slug()
                ]);
            } else {
                // return go to admin dashboard omeka s.
                return $this->redirect()->toUrl('/admin');
            }
        }

        $form = $this->getServiceLocator()->get('FormElementManager')->get(\Omeka\Form\LoginForm::class);
        $view = new ViewModel([
            'form' => $form,
            'site' => $this->currentSite()
        ]);
        $view->setTemplate('add-triplestore/site/index/login');
        
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);
           
            if (!$form->isValid()) {
                $errors = $form->getMessages();
            }
            if ($form->isValid()) {
                $validatedData = $form->getData();
                $sessionManager = Container::getDefaultManager();
                $sessionManager->regenerateId();
                
                // Use Omeka authentication
                $authService = $this->getServiceLocator()->get('Omeka\AuthenticationService');
                $adapter = $authService->getAdapter();
                $adapter->setIdentity($validatedData['email']);
                $adapter->setCredential($validatedData['password']);
                
             
                $result = $authService->authenticate();
                
                if ($result->isValid()) {
                    
                    $user = $authService->getIdentity();
                    
                    if ($user->getRole() === 'guest') {
                        return $this->redirect()->toRoute('site/add-triplestore/dashboard', [
                            'site-slug' => $this->currentSite()->slug()
                        ]);
                    } else {
                        return $this->redirect()->toUrl('/admin');
                    }
                } else {
                    $this->messenger()->addError('Email or password is invalid');
                }
            } else {
                $this->messenger()->addError('Email or password is invalid');
            }
        }
        
        return $view;
    }


    // ============= PAGE ACTION METHODS ==================

    /**
     * About Us page action
     *
     * Renders the About Us page of the AddTriplestore module.
     * @return \Laminas\View\Model\ViewModel The view model for the About Us page
     */
    public function aboutUsAction()
    {
        $view = new ViewModel();
        return $view;
    }

    /**
     * Shows the user dashboard for "guest-nonadministrative" users
     * 
     * Displays a dashboard for users with non administrative functions. Redirects admin users to the main admin dashboard.
     * This helps provide appropriate access levels and relevant information based on user roles.
     * 
     * @return \Laminas\View\Model\ViewModel|mixed Returns ViewModel for site users, redirects admins to admin dashboard
     */
    public function dashboardAction()
    {
        // Check if user is logged in
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;
        
        $user = $this->identity();
        
        // If this is an admin user, redirect to admin dashboard (omeka s)
        if ($this->userHasAdminAccess($user)) {
            return $this->redirect()->toUrl('/admin');
        }
        
        // Show dashboard
        $view = new ViewModel([
            'user' => $user,
            'site' => $this->currentSite(),
            'isLoggedIn' => true,
            'userRole' => $user->getRole(),
            'csrfToken' => $this->generateCsrfToken(),
        ]);
        $view->setTemplate('add-triplestore/site/index/user-dashboard');
        
        return $view;
    }

    /**
     * Action to provide SPARQL query interface via GraphDB.
     * 
     * This method sets up auto-login to a GraphDB instance with read-only credentials.
     * The user will be redirected to the GraphDB interface.
     * @return \Laminas\View\Model\ViewModel The view model containing GraphDB connection parameters
     */
    public function sparqlAction()
    {
        $graphdbUrl = $this->graphdbWorkbenchUrl;

        $view = new ViewModel();
        $view->setVariable('graphdbUrl', $graphdbUrl);
        $view->setTemplate('add-triplestore/site/index/sparql');

        return $view;
    }

    /**
     * My Data Action Controller
     *
     * Shows items and item sets owned by the current user.
     *
     * @return \Laminas\View\Model\ViewModel|Response
     */
    public function myDataAction()
    {
        // Check if user is logged in
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;
        
        $user = $this->identity();
        
        // If this is an admin user, redirect to admin dashboard
        if ($this->userHasAdminAccess($user)) {
            return $this->redirect()->toUrl('/admin');
        }
        
        // Get the user's items and uploads
        $userId = $user->getId();
        $items = [];
        $itemSets = [];
        
        
        try {
            // Try to get items created by user
            $response = $this->api()->search('items', [
                'owner_id' => $userId,
                'sort_by' => 'created',
                'sort_order' => 'desc',
                'limit' => 50
            ]);
            $items = $response->getContent();
   
            
            // Get item sets created by user
            $itemSetResponse = $this->api()->search('item_sets', [
                'owner_id' => $userId,
                'sort_by' => 'created', 
                'sort_order' => 'desc',
                'limit' => 50
            ]);
            $itemSets = $itemSetResponse->getContent();

            
            // Check for items in item sets owned by this user
            foreach ($itemSets as $itemSet) {
                $itemSetItems = $this->api()->search('items', [
                    'item_set_id' => $itemSet->id(),
                    'limit' => 50
                ])->getContent();
                
                foreach ($itemSetItems as $item) {
                    $found = false;
                    foreach ($items as $existingItem) {
                        if ($existingItem->id() == $item->id()) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $items[] = $item;
   
                    }
                }
            }
            
            // Remove non-archaeological items like ctx item, etc...
            $filteredItems = [];
            foreach ($items as $item) {
                $isArrowhead = false;
                
                // Check resource class
                $resourceClass = $item->resourceClass();
                if ($resourceClass && strpos(strtolower($resourceClass->label()), 'arrowhead') !== false) {
                    $isArrowhead = true;
                }
                
                // Check for arrowhead-specific properties
                if (!$isArrowhead) {
                    $values = $item->values();
                    $arrowheadProperties = [
                        'Arrowhead Shape', 'Arrowhead Variant', 'Arrowhead Base',
                        'Chipping Mode', 'Chipping Direction', 'Chipping Shape'
                    ];
                    
                    foreach ($arrowheadProperties as $property) {
                        if (isset($values[$property]) && !empty($values[$property])) {
                            $isArrowhead = true;
                            break;
                        }
                    }
                }
                
                // Check title patterns
                if (!$isArrowhead) {
                    $title = $item->displayTitle();
                    if (strpos(strtolower($title), 'arrowhead') !== false || 
                        strpos($title, 'AH-') === 0 ||
                        preg_match('/^(?:item|archaeological item)\s+AH-/i', $title)) {
                        $isArrowhead = true;
                    }
                }
                
                // Exclude known non-arrowhead item types
                if (!$isArrowhead) {
                    $title = $item->displayTitle();
                    $nonArrowheadPatterns = [
                        '/^context/i', '/^ctx-/i', '/^square/i', 
                        '/^svu/i', '/^layer-/i', '/^stratigraphic/i',
                        '/^excav/i', '/^excavation/i', '/^location/i',
                        '/^archaeological encounter/i'
                    ];
                    
                    $isNonArrowhead = false;
                    foreach ($nonArrowheadPatterns as $pattern) {
                        if (preg_match($pattern, $title)) {
                            $isNonArrowhead = true;
                            break;
                        }
                    }
                    
                    $isArrowhead = !$isNonArrowhead;
                }
                
                if ($isArrowhead) {
                    $filteredItems[] = $item;
                }
            }

            $items = $filteredItems;
            
        } catch (\Exception $e) {
   
            $this->messenger()->addError('Failed to load your items: ' . $e->getMessage());
        }
        
   
        
        // Show user contributions page
        $view = new ViewModel([
            'user' => $user,
            'site' => $this->currentSite(),
            'items' => $items,
            'itemSets' => $itemSets, 
            'totalItems' => count($items),
            'totalItemSets' => count($itemSets), 
            'isLoggedIn' => true,
            'userRole' => $user->getRole()
        ]);
        $view->setTemplate('add-triplestore/site/index/my-data');
        
        return $view;
    }

/**
     * Show details for an item or item set.
     *
     * Gets the resource by id and type, and fetches related items for item sets.
     *
     * @return \Laminas\View\Model\ViewModel|\Laminas\Http\Response
     */
    public function viewDetailsAction()
    {
        $request = $this->getRequest();
        $requestedResourceType = $request->getQuery('type', 'item'); // Rename to avoid confusion
        $requestedId = (int) $request->getQuery('id'); // Ensure it's an integer
        error_log("View details action called for resource type: $requestedResourceType, ID: $requestedId", 3, OMEKA_PATH . '/logs/countt-add-triplestore.log');

        // if no ID is provided, redirect to search page
        if (!$requestedId) {
            return $this->redirect()->toRoute('site/add-triplestore/search', ['site-slug' => $this->currentSite()->slug()]);
        }

        $resourceToDisplay = null; // This will be the actual object for the primary display
        $itemSetIdForLink = null; // This will store the correct item_set_id for links

        $properties = [];
        $relatedItems = [];
        $media = [];

        try {
            if ($requestedResourceType === 'item_set') {
                // When type is 'item_set', the requested ID IS the item_set_id
                $itemSetIdForLink = $requestedId;

                // First, try to fetch the item set itself as the primary resource to display
                $itemSetResource = $this->api()->read('item_sets', $requestedId)->getContent();

                if (!$itemSetResource) {
                    throw new \Exception("Item Set with ID {$requestedId} not found.");
                }

                $resourceToDisplay = $itemSetResource; // Default: display the item set itself

                // Optionally: Find a specific 'excavation' item within the item set for primary display
                // This part is for *displaying* a specific item, but $itemSetIdForLink remains the true item set ID.
                $excavationItems = $this->api()->search('items', [
                    'item_set_id' => $requestedId, // Use the correct requestedId here
                    'sort_by' => 'created',
                    'sort_order' => 'asc',
                    'limit' => 10
                ])->getContent();

                foreach ($excavationItems as $item) {
                    $resourceClass = $item->resourceClass();
                    if ($resourceClass && (
                        stripos($resourceClass->label(), 'excavation') !== false ||
                        stripos($item->displayTitle(), 'excavation') !== false
                    )) {
                        $resourceToDisplay = $item; // If found, display this item instead of the item set
                        break;
                    }
                }
                if (!$resourceToDisplay->resourceName() === 'items' && !empty($excavationItems)) {
                     // If resourceToDisplay is still the itemSet and there are items,
                     // perhaps default to the first item for display purposes if no specific excavation item was found.
                     // IMPORTANT: Ensure this doesn't overwrite $itemSetIdForLink
                     $resourceToDisplay = $excavationItems[0];
                }


                // Fetch all related items for this item set
                $searchParams1 = [
                    'item_set_id' => $requestedId, // Use the correct requestedId for related items
                    'sort_by' => 'created',
                    'sort_order' => 'desc',
                    'per_page' => 1000
                ];

                $response1 = $this->api()->search('items', $searchParams1);
                $relatedItems = $response1->getContent();
                error_log("Related items count: " . count($relatedItems), 3, OMEKA_PATH . '/logs/count-add-triplestore.log');
                // $totalResults1 = $response1->getTotalResults(); // Not used, can remove

                foreach ($relatedItems as $item) {
                    foreach ($item->media() as $m) {
                        if (strpos($m->mediaType(), 'image/') === 0) {
                            $media[] = $m;
                        }
                    }
                }

                // This block looks like a fallback if search('items', ['item_set_id' => $id]) fails.
                // It's less efficient as it iterates through ALL items.
                // It might indicate an underlying data model issue if the direct search fails.
                // Consider if this is truly needed or if the item_set_id search should always work.
                if (empty($relatedItems)) {
                    $allItemsResponse = $this->api()->search('items', ['per_page' => 1000]);
                    $allItems = $allItemsResponse->getContent();
                    error_log("All items count: " . count($allItems), 3, OMEKA_PATH . '/logs/count-add-triplestore.log');

                    foreach ($allItems as $item) {
                        $itemSets = $item->itemSets();
                        foreach ($itemSets as $itemSet) {
                            if ($itemSet->id() == $requestedId) { // Check against the original requestedId
                                $relatedItems[] = $item;
                            }
                        }
                    }
                }

            } else { // requestedResourceType is 'item' (Artifact)
                $resourceToDisplay = $this->api()->read('items', $requestedId)->getContent();

                if (!$resourceToDisplay) {
                    throw new \Exception("Item with ID {$requestedId} not found.");
                }

                // If viewing an item, we still need its item_set_id if it belongs to one
                // for any 'Add artifacts to this excavation' type links in the sidebar
                // (though that section will likely be hidden for 'item' view)
                $itemSets = $resourceToDisplay->itemSets();
                if (!empty($itemSets) && isset($itemSets[0]) && is_object($itemSets[0])) {
                $itemSetIdForLink = $itemSets[0]->id(); // Take the first item set ID this item belongs to
                } else {
                    $itemSetIdForLink = null; // Explicitly set to null if no item set is found
                }

                foreach ($resourceToDisplay->media() as $m) {
                    if (strpos($m->mediaType(), 'image/') === 0) {
                        $media[] = $m;
                    }
                }
            }

            if (!$resourceToDisplay) {
                throw new \Exception("Resource could not be loaded.");
            }

            $values = $resourceToDisplay->values(); // Get properties from the resource being displayed

            foreach ($values as $term => $propertyData) {
                try {
                    if (empty($propertyData)) {
                        continue;
                    }

                    $propertyLabel = $this->vocabularyLabelService->getHumanReadableLabel($term);
                    $propertyValues = [];

                    if (is_array($propertyData)) {
                        if (isset($propertyData['values']) && is_array($propertyData['values'])) {
                            $propertyValues = $propertyData['values'];
                            if (isset($propertyData['property']) && is_object($propertyData['property'])) {
                                if (method_exists($propertyData['property'], 'label')) {
                                    $propertyLabel = $propertyData['property']->label();
                                }
                            }
                        } else {
                            foreach ($propertyData as $item) {
                                if (is_object($item) && method_exists($item, 'value')) {
                                    $propertyValues[] = $item;
                                    if (empty($propertyValues) && method_exists($item, 'property')) {
                                        $prop = $item->property();
                                        if ($prop && method_exists($prop, 'label')) {
                                            $propertyLabel = $prop->label();
                                        }
                                    }
                                }
                            }
                        }
                    } else if (is_object($propertyData) && method_exists($propertyData, 'value')) {
                        $propertyValues = [$propertyData];
                        if (method_exists($propertyData, 'property')) {
                            $prop = $propertyData->property();
                            if ($prop && method_exists($prop, 'label')) {
                                $propertyLabel = $prop->label();
                            }
                        }
                    }

                    if (!empty($propertyValues)) {
                        $properties[] = [
                            'term' => $term,
                            'label' => $propertyLabel,
                            'values' => $propertyValues
                        ];
                    }

                } catch (\Exception $e) {
                    // Log the exception if needed, but continue processing other properties
                    error_log("Error processing property {$term}: " . $e->getMessage(), 3, OMEKA_PATH . '/logs/countt-add-triplestore.log');
                    continue;
                }
            }

            usort($properties, function($a, $b) {
                if ($a['label'] === 'Title') return -1;
                if ($b['label'] === 'Title') return 1;
                return strcmp($a['label'], $b['label']);
            });

        } catch (\Exception $e) {
            $this->messenger()->addError('The requested resource could not be found: ' . $e->getMessage());
            return $this->redirect()->toRoute('site/add-triplestore/search', ['site-slug' => $this->currentSite()->slug()]);
        }

        error_log("Final VIEW MODEL: resourceType: $requestedResourceType, requestedId: $requestedId, resourceToDisplay ID: " . ($resourceToDisplay ? $resourceToDisplay->id() : 'N/A') . ", itemSetIdForLink: " . ($itemSetIdForLink ?? 'N/A'), 3, OMEKA_PATH . '/logs/countt-add-triplestore.log');

        return new ViewModel([
            'resource' => $resourceToDisplay, // Pass the resource intended for display (could be item set or item)
            'resourceType' => $requestedResourceType, // The type requested by the URL
            'itemSetIdForLink' => $itemSetIdForLink, // THIS IS THE KEY: The correct item set ID for "Add Artifacts" links
            'properties' => $properties,
            'relatedItems' => $relatedItems,
            'media' => $media,
            'site' => $this->currentSite()
        ]);
    }

    // ================== FORMS  ==================

    /**
     * Process the collecting form submission.
     *
     * This action handles the submission of the collecting form, transforms the data into Arrowhead format and uploads it to GraphDB.
     *
     * @return \Laminas\Http\Response|\Laminas\View\Model\ViewModel
     */
    public function processCollectingFormAction()
    {
        $redirect = $this->requireLogin();
        if ($redirect)
            return $redirect;

        if ($this->getRequest()->isPost() && !$this->validateCsrfToken()) {
            return $this->redirect()->toRoute('site/add-triplestore/upload', [
                'site-slug' => $this->currentSite()->slug(),
            ]);
        }

        $itemSetId = $this->params()->fromQuery('item_set_id');
        error_log("Processing collecting form for item set ID: $itemSetId", 3, OMEKA_PATH . '/logs/count-add-triplestore.log');
        $uploadType = $this->params()->fromQuery('upload_type', 'arrowhead');
        
        // Get data from the collecting form
        $formData = $this->params()->fromPost();
                
        $uploadedFiles = null;
        if (isset($_FILES['file']['54'])) {
            $uploadedFiles = $_FILES['file']['54'];
        }
        // transform the form data to Arrowhead data format
        $arrowheadData = $this->collectingFormToArrowheadMapper->map($formData);

        
        if (!empty($arrowheadData)) {
            $ttlData = $this->processArrowheadFormData($arrowheadData, $itemSetId);

            $result = $this->uploadTtlDataWithMedia($ttlData, $itemSetId, $uploadedFiles);   
            
            
            // Redirect to excavation context with success message
            return $this->redirect()->toUrl($this->url()->fromRoute('site/add-triplestore/upload', [
                'site-slug' => $this->currentSite()->slug(),
            ], [
                'query' => [
                    'upload_type' => 'arrowhead',
                    'item_set_id' => $itemSetId,
                    'mode' => 'form',
                    'result' => $result,
                    'success' => '1'
                ]
            ]));
        }

        // If failed, redirect with error
        return $this->redirect()->toUrl($this->url()->fromRoute('site/add-triplestore/upload', [
            'site-slug' => $this->currentSite()->slug(),
        ], [
            'query' => [
                'upload_type' => 'arrowhead',
                'item_set_id' => $itemSetId,
                'mode' => 'form',
                'result' => 'Error: Could not process form data'
            ]
        ]));
    }

    // ================== UPLOAD ACTION ==================

    /**
     * upload handler for AddTriplestore.
     *
     * Handles arrowhead and excavation uploads via file or form, creates item sets as needed nand redirects or renders the appropriate view. Includes authentication and permission checks.
     *
     * @return mixed ViewModel or redirect response
     */

    public function uploadAction()
    {
        $redirect = $this->requireLogin();
        if ($redirect) return $redirect;

        $user = $this->identity();

        if ($this->getRequest()->isPost() && !$this->validateCsrfToken()) {
            return $this->redirect()->toRoute('site/add-triplestore/upload', [
                'site-slug' => $this->currentSite()->slug(),
            ]);
        }

        $postData = $this->params()->fromPost();

        // Check if is a continuous arrowhead upload (file upload or form submission)
        $uploadType = $this->params()->fromQuery('upload_type') ?: $this->params()->fromPost('upload_type');
        $itemSetId = $this->params()->fromQuery('item_set_id') ?: $this->params()->fromPost('item_set_id');
        $mode = $this->params()->fromQuery('mode', $this->params()->fromPost('mode', 'upload'));

        
        // Process arrowhead file upload
        if ($mode == 'file' && $uploadType == 'arrowhead' && $itemSetId) {
            $file = $this->params()->fromFiles('file');
            if ($file && !empty($file['tmp_name'])) {

                $result = $this->processFileUpload($this->getRequest(), $uploadType, $itemSetId);

                $excavationId = $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($itemSetId);

                if ($excavationId && strpos($result, 'successfully') !== false) {
                    $result = "Arrowhead was successfully added to excavation $excavationId (Item Set #$itemSetId). You can upload another or click Exit when done.";
                }
                
                // Redirect back to upload page to enable continuous uploads
                $url = $this->url()->fromRoute('site/add-triplestore/upload', [
                    'site-slug' => $this->currentSite()->slug(),
                ], [
                    'query' => [
                        'upload_type' => 'arrowhead',
                        'item_set_id' => $itemSetId,
                        'mode' => 'file',
                        'result' => $result
                    ]
                ]);
                return $this->redirect()->toUrl($url);
            }
            
            $view = new ViewModel([
                'itemSetId' => $itemSetId,
                'uploadType' => $uploadType,
                'result' => $this->params()->fromQuery('result'),
                'csrfToken' => $this->generateCsrfToken(),
            ]);
            $view->setTemplate('add-triplestore/site/index/upload-arrowhead');
            return $view;
        }

        // arrowhead form processing
        if ($mode == 'form' && $uploadType == 'arrowhead') {

            $formData = $this->params()->fromPost();
            
            $success = $this->params()->fromQuery('success', false);
            
            if (!empty($formData) && empty($success)) {

                // If this is a form submission, process the data
                $ttlData = $this->processArrowheadFormData($formData, $itemSetId);

                $result = $this->uploadTtlData($ttlData, $itemSetId) ?? 'Unknown error occurred during upload';
                
                // Redirect to success page
                $url = $this->url()->fromRoute('site/add-triplestore/upload', [
                    'site-slug' => $this->currentSite()->slug(),
                ], [
                    'query' => [
                        'upload_type' => 'arrowhead',
                        'item_set_id' => $itemSetId,
                        'mode' => 'form',
                        'result' => $result,
                        'success' => '1'
                    ]
                ]);
                
                return $this->redirect()->toUrl($url);
            } else {
                
                $view = new ViewModel([
                    'itemSetId' => $itemSetId,
                    'uploadType' => $uploadType,
                    'result' => $this->params()->fromQuery('result', ''),
                    'success' => $success,
                    'csrfToken' => $this->generateCsrfToken(),
                ]);
                $view->setTemplate('add-triplestore/site/index/upload-arrowhead');
                return $view;
            }
        }

        // Process the excavation form submission
        if ($uploadType == 'excavation' && !isset($_FILES['file'])) {

            $formData = $this->params()->fromPost();
                        
            // Transform collecting form data to excavation format
            $excavationData = $this->collectingFormToExcavationDataMapper->mapFromPost($formData);
            
            if (!empty($excavationData)) {

                $excavationIdentifier = $excavationData['excavation_id'] ?? null;
                
                
                // ttlData processing
                $ttlData = $this->excavationTtlBuilder->buildFromFormData($excavationData, $excavationIdentifier);

                try {
                    $itemSetId = $this->omekaSiteResourceService->createItemSetFromCollectedExcavationForm((string) $excavationIdentifier, $excavationData);

                    $this->excavationItemSetContextService->storeMappingBetweenItemSetAndExcavation($itemSetId, $excavationIdentifier);

                    $result = $this->uploadTtlData($ttlData, $itemSetId);

                    return $this->redirect()->toUrl($this->url()->fromRoute('site/add-triplestore/upload', [
                        'site-slug' => $this->currentSite()->slug(),
                    ], [
                        'query' => [
                            'upload_type' => 'arrowhead',
                            'item_set_id' => $itemSetId,
                            'mode' => 'file',
                            'result' => $result,
                        ],
                    ]));
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), 'permission') !== false) {
                        $this->messenger()->addError('You do not have permission to create excavations. Please contact an administrator.');
                    } else {
                        $this->messenger()->addError('Failed to create excavation: ' . $e->getMessage());
                    }
                    if (!$this->canUserCreateResource('ItemSet')) {
                        $this->messenger()->addError('You do not have permission to create excavations.');
                        return $this->redirect()->toRoute('site/add-triplestore/dashboard', [
                            'site-slug' => $this->currentSite()->slug()
                        ]);
                    }
                    return $this->redirect()->toRoute('site/add-triplestore/dashboard', [
                        'site-slug' => $this->currentSite()->slug()
                    ]);
                }
            }
            
            // If transformation failed rediect with error
            return $this->redirect()->toUrl($this->url()->fromRoute('site', [
                'site-slug' => $this->currentSite()->slug()
            ], [
                'query' => [
                    'result' => 'Error: Could not process excavation form data'
                ]
            ]));
        }
        
        // direct file uploads
        else if (isset($_FILES['file']) && !empty($_FILES['file']['tmp_name'])) {
            
            $result = $this->processFileUpload($this->getRequest(), $uploadType, $itemSetId);
            // If error, redirect back to upload page with error message
            if (strpos($result, 'Error') !== false || strpos($result, 'Validation Error') !== false || strpos($result, 'Failed') !== false) {
                $url = $this->url()->fromRoute('site/add-triplestore/upload', [
                    'site-slug' => $this->currentSite()->slug(),
                ], [
                    'query' => [
                        'upload_type' => $uploadType,
                        'item_set_id' => $itemSetId,
                        'mode' => $mode,
                        'result' => $result
                    ]
                ]);
                return $this->redirect()->toUrl($url);
            }
            if ($uploadType == 'excavation') {

                preg_match('/Excavation ([A-Za-z0-9-]+)/', $result, $matches);
                $excavationIdentifier = isset($matches[1]) ? $matches[1] : null;

                if ($excavationIdentifier) {
                    // Get the item set id 
                    if (strpos($result, 'Item Set #') !== false) {
                        preg_match('/Item Set #(\d+)/', $result, $matches);
                        $itemSetId = isset($matches[1]) ? $matches[1] : null;
                    }
                    
                    if ($itemSetId) {

                        // Redirect to the arrowhead upload form with the excavation context
                        return $this->redirect()->toUrl($this->url()->fromRoute('site/add-triplestore/upload', [
                            'site-slug' => $this->currentSite()->slug(),
                        ], [
                            'query' => [
                                'upload_type' => 'arrowhead',
                                'item_set_id' => $itemSetId,
                                'mode' => 'file',
                                'result' => $result
                            ]
                        ]));
                    }
                }
            }
            
            // For excavation file uploads
            if ($uploadType == 'excavation' && strpos($result, 'successfully') !== false) {
                // extract item set id from the result
                preg_match('/Item Set #(\d+)/', $result, $matches);
                $newItemSetId = isset($matches[1]) ? $matches[1] : null;
                
                if ($newItemSetId) {
                    return $this->redirect()->toUrl($this->url()->fromRoute('site/add-triplestore/upload', [
                        'site-slug' => $this->currentSite()->slug(),
                    ], [
                        'query' => [
                            'upload_type' => 'arrowhead',
                            'item_set_id' => $newItemSetId,
                            'mode' => 'file',
                            'result' => $result
                        ]
                    ]));
                }
            }
            
            // redirect to the index page with the result
            return $this->redirect()->toUrl($this->url()->fromRoute('site', [
                'site-slug' => $this->currentSite()->slug()
            ], [
                'query' => [
                    'result' => $result,
                    'item_set_id' => $itemSetId
                ]
            ]));
        }
        
        // Default if no specific upload type was recognized
        return $this->redirect()->toUrl($this->url()->fromRoute('site', ['site-slug' => $this->currentSite()->slug()]));
    }


    // ================== DOWNLOAD METHODS ==================

    /**
     * Download template for arrowhead or excavation data.
     *
     * This action allows users to download a template file in either Turtle (TTL) or XML format for each ttype of data.
     *
     * @return \Laminas\Http\Response
     */
    public function downloadTemplateAction()
    {
        // Get template type and format
        $templateType = $this->params()->fromQuery('template', 'arrowhead'); // 'arrowhead' or 'excavation'
        $format = $this->params()->fromQuery('format', 'ttl'); // 'ttl' or 'xml'

        // Validate inupt
        $allowedTemplates = ['arrowhead', 'excavation'];
        $allowedFormats = ['ttl', 'xml'];

        if (!in_array($templateType, $allowedTemplates) || !in_array($format, $allowedFormats)) {
            $this->messenger()->addError('Invalid template or format requested.');
            return $this->redirect()->toRoute('site/add-triplestore/upload', [
                'site-slug' => $this->currentSite()->slug()
            ]);
        }

        $filename = "{$templateType}.{$format}";
        $filePath = OMEKA_PATH . '/modules/AddTriplestore/asset/templates/' . $filename;

        if (!file_exists($filePath)) {
            $this->messenger()->addError('Template file not found.');
            return $this->redirect()->toRoute('site/add-triplestore/upload', [
                'site-slug' => $this->currentSite()->slug()
            ]);
        }

        // Set content type
        $contentType = $format === 'xml' ? 'application/xml' : 'text/turtle';

        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', $contentType);
        $response->getHeaders()->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setContent(file_get_contents($filePath));
        
        return $response;
    }

    /**
     * Download TTL data for an item or item set.
     * @return \Laminas\Http\Response|\Laminas\Stdlib\ResponseInterface
     */
    public function downloadTtlAction()
    {
        $id = $this->params()->fromQuery('id');
        $type = $this->params()->fromQuery('type', 'item');
        
        if (empty($id)) {
            return $this->redirect()->toRoute('site/add-triplestore/search', ['site-slug' => $this->currentSite()->slug()]);
        }
        
        try {
            // Get the resource to extract
            $resourceType = $type === 'item_set' ? 'item_sets' : 'items';
            $resource = $this->api()->read($resourceType, $id)->getContent();
            
            if ($type === 'item_set') {
                $ttlData = $this->ttlPresentationService->queryCompleteExcavationFromGraphDB($id, $resource);
            } else {
                $ttlData = null;
                if ($this->isLikelyArrowheadResource($resource)) {
                    $exporterOut = trim($this->arrowheadCanonicalTtlExporter->generateFromItemRepresentation($resource));
                    if (strpos($exporterOut, '# No valid excavation context') !== 0) {
                        $ttlData = $exporterOut;
                    }
                }
                if ($ttlData === null || $ttlData === '') {
                    $ttlData = $this->ttlPresentationService->queryItemFromGraphDB($resource, (int) $id);
                }
            }
            
            if (empty($ttlData)) {
                $this->messenger()->addError('No TTL data found for this resource.');
                return $this->redirect()->toRoute('site/add-triplestore/view-details', 
                    ['site-slug' => $this->currentSite()->slug()],
                    ['query' => ['id' => $id, 'type' => $type]]
                );
            }

            $filename = $this->ttlUriHelper->sanitizeFilename($resource->displayTitle());
            
            $response = $this->getResponse();
            $response->getHeaders()->addHeaderLine('Content-Type', 'text/turtle; charset=UTF-8');
            $response->getHeaders()->addHeaderLine('Content-Disposition', 'attachment; filename="' . $filename . '.ttl"');
            $response->setContent($ttlData);
            
            return $response;
            
        } catch (\Exception $e) {
            $this->messenger()->addError('Error generating TTL data: ' . $e->getMessage());
            return $this->redirect()->toRoute('site/add-triplestore/view-details', 
                ['site-slug' => $this->currentSite()->slug()],
                ['query' => ['id' => $id, 'type' => $type]]
            );
        }
    }






    // ================== SEARCH ACTIONS ==================

/**
     * This method handles the search functionality for items and item sets.
     * @return ViewModel
     */
    public function searchAction()
    {
        // Check if user is logged in
        $request = $this->getRequest();
        $searchQuery = $request->getQuery('query', '');
        $searchType = $request->getQuery('type', 'all'); // 'items', 'item_sets', or 'all'
        $page = $request->getQuery('page', 1);
        $perPage = 20;
        
        // Excavation filters
        $filterArchaeologist = $request->getQuery('archaeologist', '');
        $filterOrcid = $request->getQuery('orcid', '');
        $filterCountry = $request->getQuery('country', '');
        $filterDistrict = $request->getQuery('district', '');
        $filterParish = $request->getQuery('parish', '');
        
        // Basic arrowhead filters
        $filterShape = $request->getQuery('shape', '');
        $filterVariant = $request->getQuery('variant', '');
        $filterMaterial = $request->getQuery('material', '');
        $filterElongation = $request->getQuery('elongation', '');
        
        // morphological filters
        $filterThickness = $request->getQuery('thickness', '');
        $filterBase = $request->getQuery('base', '');
        $filterCondition = $request->getQuery('condition', '');
        
        // Chipping filters
        $filterChippingMode = $request->getQuery('chippingMode', '');
        $filterChippingDirection = $request->getQuery('chippingDirection', '');
        $filterChippingDelineation = $request->getQuery('chippingDelineation', '');
        $filterChippingShape = $request->getQuery('chippingShape', '');
        $filterChippingAmplitude = $request->getQuery('chippingAmplitude', '');
        
        // Size and weight filters
        $minHeight = $request->getQuery('minHeight', '');
        $maxHeight = $request->getQuery('maxHeight', '');
        $minWidth = $request->getQuery('minWidth', '');
        $maxWidth = $request->getQuery('maxWidth', '');
        $minThickness = $request->getQuery('minThickness', '');
        $maxThickness = $request->getQuery('maxThickness', '');
        $minWeight = $request->getQuery('minWeight', '');
        $maxWeight = $request->getQuery('maxWeight', '');
        
        $results = [];
        $totalItems = 0;
        $totalItemSets = 0;
        
        // retireve filter options from GraphDB
        $archaeologistOptions = $this->siteMetadataOptionsService->getArchaeologistOptions();
        $countryOptions = $this->siteMetadataOptionsService->getCountryOptions();
        $districtOptions = $this->siteMetadataOptionsService->getDistrictOptions();
        $parishOptions = $this->siteMetadataOptionsService->getParishOptions();
        
        // Prepare the results array with filter options
        $hasFilters = $filterShape || $filterVariant || $filterMaterial || $filterElongation || 
                $filterThickness || $filterBase || $filterCondition || $filterChippingMode || 
                $filterChippingDirection || $filterChippingDelineation || $filterChippingShape || 
                $filterChippingAmplitude || $minHeight || $maxHeight || $minWidth || $maxWidth || 
                $minThickness || $maxThickness || $minWeight || $maxWeight ||
                $filterArchaeologist || $filterOrcid || $filterCountry || $filterDistrict || $filterParish;

        if ($searchQuery || $hasFilters) {
            if ($searchType === 'all' || $searchType === 'item_sets') {
                $itemSetQuery = [];
        
                // search query
                if ($searchQuery) {
                    $itemSetQuery['fulltext_search'] = $searchQuery;
                }
        
                // If we have excavation filters, search excavation items first
                if ($filterArchaeologist || $filterOrcid || $filterCountry || $filterDistrict || $filterParish) {
                    // Search for excavation items
                    
                    $excavationItemQuery = [];
                    $propertyFilters = [];
                    
                    if ($filterArchaeologist) {
                        $propertyFilters[] = [
                            'property' => 7665, // Person in Charge property ID
                            'type' => 'in',
                            'text' => $filterArchaeologist
                        ];
                    }
                    
                    if ($filterOrcid) {
                        $propertyFilters[] = [
                            'property' => 176, // ORCID property ID
                            'type' => 'eq',
                            'text' => $filterOrcid
                        ];
                    }
                    
                    if ($filterCountry) {
                        $propertyFilters[] = [
                            'property' => 1402, // Country property ID
                            'type' => 'eq',
                            'text' => $filterCountry
                        ];
                    }
                    
                    if ($filterDistrict) {
                        $propertyFilters[] = [
                            'property' => 1555, // district property ID
                            'type' => 'eq',
                            'text' => $filterDistrict
                        ];
                    }
                    
                    if ($filterParish) {
                        $propertyFilters[] = [
                            'property' => 1681, // parish property ID
                            'type' => 'eq',
                            'text' => $filterParish
                        ];
                    }
                    
                    if (!empty($propertyFilters)) {
                        $excavationItemQuery['property'] = $propertyFilters;
                    }
                    
                    // filter by title pattern to only get excavation items
                    $excavationItemQuery['fulltext_search'] = 'Excavation';
                                        
                    // Search for excavation items
                    $excavationItemsResponse = $this->api()->search('items', $excavationItemQuery);
                    $excavationItems = $excavationItemsResponse->getContent();
                                        
                    // Extract item set id from the excavation items
                    $itemSetIds = [];
                    foreach ($excavationItems as $item) {
                        $itemSets = $item->itemSets();
                        foreach ($itemSets as $itemSet) {
                            $itemSetIds[] = $itemSet->id();
                        }
                    }
                    
                    // Remove duplicates
                    $itemSetIds = array_unique($itemSetIds);
                    
                    
                    if (!empty($itemSetIds)) {

                        $itemSetQuery['id'] = $itemSetIds;
                        
                        // add the search query if provided
                        if ($searchQuery) {
                            unset($itemSetQuery['fulltext_search']);
                        }
                                                
                        $itemSetsResponse = $this->api()->search('item_sets', $itemSetQuery);
                        $results['item_sets'] = $itemSetsResponse->getContent();
                        $totalItemSets = $itemSetsResponse->getTotalResults();
                    } else {
                        $results['item_sets'] = [];
                        $totalItemSets = 0;
                    }
                } else {
                    // search item sets
                    $itemSetsResponse = $this->api()->search('item_sets', $itemSetQuery);
                    $results['item_sets'] = $itemSetsResponse->getContent();
                    $totalItemSets = $itemSetsResponse->getTotalResults();
                }
            }
            if ($searchType === 'all' || $searchType === 'items') {
                $itemQuery = [];
                
                //  search query
                if ($searchQuery) {
                    if ($searchQuery === 'arrowhead') {
                        $itemQuery['fulltext_search'] = 'arrowhead* OR "archaeological item*"';
                    } else {
                        $itemQuery['fulltext_search'] = $searchQuery;
                    }
                }
                
                // Apply arrowhead filters
                $propertyFilters = [];
                
                if ($filterShape) {
                    $propertyFilters[] = [
                        'property' => 7651,  // Arrowhead Shape property ID
                        'type' => 'eq',
                        'text' => $filterShape
                    ];
                }
                
                if ($filterVariant) {
                    $propertyFilters[] = [
                        'property' => 7652,  // Arrowhead Variant property ID
                        'type' => 'eq',
                        'text' => $filterVariant
                    ];
                }
                
                if ($filterMaterial) {
                    $propertyFilters[] = [
                        'property' => 4633,  // Material property ID
                        'type' => 'eq',
                        'text' => $filterMaterial
                    ];
                }
                
                if ($filterElongation) {
                    $propertyFilters[] = [
                        'property' => 7676,  // Elongation Index property ID
                        'type' => 'eq',
                        'text' => $filterElongation
                    ];
                }
                
                if ($filterThickness) {
                    $propertyFilters[] = [
                        'property' => 7677,  // Thickness Index property ID
                        'type' => 'eq',
                        'text' => $filterThickness
                    ];
                }
                
                if ($filterBase) {
                    $propertyFilters[] = [
                        'property' => 7653,  // Base Type property ID
                        'type' => 'eq',
                        'text' => $filterBase
                    ];
                }
                
                if ($filterCondition !== '') {
                    $propertyFilters[] = [
                        'property' => 476,  // Condition State property ID
                        'type' => 'eq',
                        'text' => $filterCondition
                    ];
                }
                
                if ($filterChippingMode) {
                    $propertyFilters[] = [
                        'property' => 7656,  // Chipping Mode property ID
                        'type' => 'eq',
                        'text' => $filterChippingMode
                    ];
                }
                
                if ($filterChippingDirection) {
                    $propertyFilters[] = [
                        'property' => 7658,  // Chipping Direction property ID
                        'type' => 'eq',
                        'text' => $filterChippingDirection
                    ];
                }
                
                if ($filterChippingDelineation) {
                    $propertyFilters[] = [
                        'property' => 7660,  // Chipping Delineation property ID
                        'type' => 'eq',
                        'text' => $filterChippingDelineation
                    ];
                }
                
                if ($filterChippingShape) {
                    $propertyFilters[] = [
                        'property' => 7661,  // Chipping Shape property ID
                        'type' => 'eq',
                        'text' => $filterChippingShape
                    ];
                }
                
                if ($filterChippingAmplitude !== '') {
                    $propertyFilters[] = [
                        'property' => 7657,  // Chipping Amplitude property ID
                        'type' => 'eq',
                        'text' => $filterChippingAmplitude
                    ];
                }
                
                if ($minHeight) {
                    $propertyFilters[] = [
                        'property' => 5616,  // Height property ID
                        'type' => 'gte',
                        'text' => $minHeight
                    ];
                }
                
                if ($maxHeight) {
                    $propertyFilters[] = [
                        'property' => 5616,  // Height property ID
                        'type' => 'lte',
                        'text' => $maxHeight
                    ];
                }
                
                if ($minWidth) {
                    $propertyFilters[] = [
                        'property' => 5688,  // Width property ID
                        'type' => 'gte',
                        'text' => $minWidth
                    ];
                }
                
                if ($maxWidth) {
                    $propertyFilters[] = [
                        'property' => 5688,  // Width property ID
                        'type' => 'lte',
                        'text' => $maxWidth
                    ];
                }
                
                if ($minThickness) {
                    $propertyFilters[] = [
                        'property' => 7244,  // Thickness property ID
                        'type' => 'gte',
                        'text' => $minThickness
                    ];
                }
                
                if ($maxThickness) {
                    $propertyFilters[] = [
                        'property' => 7244,  // Thickness property ID
                        'type' => 'lte',
                        'text' => $maxThickness
                    ];
                }
                
                if ($minWeight) {
                    $propertyFilters[] = [
                        'property' => 5779,  // Weight property ID
                        'type' => 'gte',
                        'text' => $minWeight
                    ];
                }
                
                if ($maxWeight) {
                    $propertyFilters[] = [
                        'property' => 5779,  // Weight property ID
                        'type' => 'lte',
                        'text' => $maxWeight
                    ];
                }
                
                if (!empty($propertyFilters)) {
                    $itemQuery['property'] = $propertyFilters;
                }
                
                // Execute the items search
                $itemsResponse = $this->api()->search('items', $itemQuery);
                $results['items'] = $itemsResponse->getContent();
                $totalItems = $itemsResponse->getTotalResults();
            }
        }
        
        $totalResults = $totalItems + $totalItemSets;
        
        return new ViewModel([
            'site' => $this->currentSite(),
            'searchQuery' => $searchQuery,
            'searchType' => $searchType,
            'results' => $results,
            'totalResults' => $totalResults,
            'totalItems' => $totalItems,
            'totalItemSets' => $totalItemSets,
            'archaeologistOptions' => $archaeologistOptions,
            'countryOptions' => $countryOptions,
            'districtOptions' => $districtOptions,
            'parishOptions' => $parishOptions,
            'filterArchaeologist' => $filterArchaeologist,
            'filterOrcid' => $filterOrcid,
            'filterCountry' => $filterCountry,
            'filterDistrict' => $filterDistrict,
            'filterParish' => $filterParish,
        ]);
    }

    // ================== PREvent METHODS ==================

    /**
     * Pre-dispatch access control.
     * Calls parent preDispatch if available and prevents site-only users from accessing admin areas.
     */
    public function preDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        // Call parent preDispatch if it exists
        if (method_exists(get_parent_class(), 'preDispatch')) {
            parent::preDispatch($e);
        }
        
        $this->preventAdminAccess($e);
    }

    /**
     * Prevent site-only users from accessing admin areas.
     *
     * Redirects users with the 'guest' role away from admin sections.
     *
     * @param \Laminas\Mvc\MvcEvent $e
     */
    private function preventAdminAccess(\Laminas\Mvc\MvcEvent $e)
    {
        $request = $e->getRequest();
        $uri = $request->getUri();
        $path = $uri->getPath();
        
        // Check if user is trying to access admin areas
        $adminPaths = ['/admin', '/api', '/application'];
        
        $isAdminPath = false;
        foreach ($adminPaths as $adminPath) {
            if (strpos($path, $adminPath) === 0) {
                $isAdminPath = true;
                break;
            }
        }
        
        if ($isAdminPath && $this->identity()) {
            $user = $this->identity();
            
            // If user is a site only user redirct away from admin
            if ($user->getRole() === 'guest') {
                $this->messenger()->addError('Access denied. You do not have permission to access administrative areas.');
                
                // Redirect to allowed site
                $session = new Container('site_user');
                $siteSlug = $session->allowedSite ?: $this->currentSite()->slug();
                
                $response = $e->getResponse();
                $response->getHeaders()->addHeaderLine('Location', $this->url()->fromRoute('site', ['site-slug' => $siteSlug]));
                $response->setStatusCode(302);
                return $response;
            }
        }
    }
 


    /**
     * Get the application service manager.
     *
     * @return \Laminas\ServiceManager\ServiceManager
     */
    private function getServiceLocator()
    {
        $serviceManager = $this->getEvent()->getApplication()->getServiceManager();
        return $serviceManager;
    }



    /**
     * Checks if the current user has admin access to the dashboard.
     * If the user is not logged in, it returns false.
     *
     * @param \Omeka\Entity\User|null $user The user entity or null if not logged in
     * @return bool True if the user has admin access, false otherwise
     */
    private function userHasAdminAccess($user)
    {
        if (!$user) {
            return false;
        }
        
        $role = $user->getRole();
        
        $adminRoles = ['global_admin', 'site_admin', 'editor', 'reviewer', 'author'];
        
        return in_array($role, $adminRoles);
    }







    /**
     * Checks if the current user is logged in.
     * If not, it redirects to the login page with an error message.
     *
     * @return \Laminas\Http\Response|null Returns null if the user is logged in, otherwise redirects to login
     */
    private function requireLogin()
    {
        if (!$this->identity()) {
            $this->messenger()->addError('You must log in to access this page');
            return $this->redirect()->toRoute('site/add-triplestore/login', [
                'site-slug' => $this->currentSite()->slug()
            ]);
        }
        
        // Log the current user for debugging
        $user = $this->identity();
    
        
        // Allow guest users to proceed
        return null;
    }

    private function getCsrfValidator()
    {
        if (!$this->csrfValidator) {
            $this->csrfValidator = new CsrfValidator([
                'name' => 'add_triplestore_csrf',
                'timeout' => 3600,
            ]);
        }
        return $this->csrfValidator;
    }

    private function generateCsrfToken()
    {
        return $this->getCsrfValidator()->getHash();
    }

    private function validateCsrfToken()
    {
        $token = $this->params()->fromPost('csrf_token', '');
        if (!$this->getCsrfValidator()->isValid($token)) {
            $this->messenger()->addError('Invalid or expired form submission. Please try again.');
            return false;
        }
        return true;
    }


    /**
     * Get the signup form for creating a new site-only user.
     *
     * @return \Laminas\Form\Form
     */
    private function getSignupForm()
    {
        $form = new \Laminas\Form\Form('signup');
        $form->setAttribute('method', 'post');
        
        $form->add([
            'name' => 'name',
            'type' => 'text',
            'options' => [
                'label' => 'Full Name'
            ],
            'attributes' => [
                'required' => true,
                'class' => 'form-control'
            ]
        ]);
        
        $form->add([
            'name' => 'email',
            'type' => 'email',
            'options' => [
                'label' => 'Email'
            ],
            'attributes' => [
                'required' => true,
                'class' => 'form-control'
            ]
        ]);
        
        $form->add([
            'name' => 'password',
            'type' => 'password',
            'options' => [
                'label' => 'Password'
            ],
            'attributes' => [
                'required' => true,
                'class' => 'form-control'
            ]
        ]);
        
        $form->add([
            'name' => 'confirm_password',
            'type' => 'password',
            'options' => [
                'label' => 'Confirm Password'
            ],
            'attributes' => [
                'required' => true,
                'class' => 'form-control'
            ]
        ]);
        
        $form->add([
            'name' => 'submit',
            'type' => 'submit',
            'attributes' => [
                'value' => 'Create Account',
                'class' => 'btn btn-primary'
            ]
        ]);
        
        return $form;
    }


    // ================== UTILITY METHODS ==================

/**
 * This method checks the user's permissions against the ACL service to determine if they can create the specfied resource type.
 *
 * @param string $resourceType The type of resource to check (e.g., 'Item', 'ItemSet')
 * @return bool True if the user can create the resource, false otherwise
 */
private function canUserCreateResource($resourceType) 
{
    // user must be logged in to check permissions
    $user = $this->identity();
    if (!$user) {
        return false;
    }
    
    // Get the ACL service
    $acl = $this->getServiceLocator()->get('Omeka\Acl');
    
    // Check if the user has permission to create this resource type
    $canCreate = $acl->userIsAllowed("Omeka\Entity\\$resourceType", 'create');
    

    return $canCreate;
    }


    /**
     * Processes the form data for the arrowhead item.
     * This includes extracting relevant information and generating RDF triples.
     * @param array $formData The form data submitted for the arrowhead
     * @param string $itemSetId The ID of the item set
     * @return string Generated Turtle (RDF)
     */
    private function processArrowheadFormData($formData, $itemSetId)
    {
        [$excavationIdentifier, $realLocationUri, $locationData] = $this->excavationItemSetContextService->resolveForArrowheadTtl((string) $itemSetId);

        return $this->arrowheadTtlBuilder->buildFromFormData(
            $formData,
            (string) $itemSetId,
            $excavationIdentifier,
            $realLocationUri,
            $locationData
        );
    }


/**
 * Uploads the arrowhead data and associated media files.
 * This method processes the form data, generates RDF triples, and uploads the data to the specified item set.
 * @param string $ttlData
 * @param string $itemSetId The ID of the item set to upload to
 * @param array $uploadedFiles Optional array of uploaded files
 * @return mixed
 */
private function uploadTtlDataWithMedia($ttlData, $itemSetId, $uploadedFiles) {
    $this->uploadedFiles = $uploadedFiles;
    
    return $this->uploadTtlData($ttlData, $itemSetId);
}




/**
 * This function processes the upload of a file
 * @param mixed $request
 * @param mixed $uploadType
 * @param mixed $itemSetId
 * @throws \Exception
 * @return string
 */
private function processFileUpload($request, ?string $uploadType, ?int $itemSetId): string
{
    $file = $request->getFiles()->file;
    if (empty($file['tmp_name'])) {
        return 'No file uploaded or file upload error.';
    }

    $normalizedType = $this->uploadedFileToTtlConverter->resolveNormalizedMime(
        (string) ($file['name'] ?? ''),
        (string) ($file['type'] ?? '')
    );
    if (!$this->uploadedFileToTtlConverter->isAllowedUploadMime($normalizedType)) {
        return 'Invalid file type. Please upload a valid .ttl or .xml file.';
    }

    $fileWithType = $file;
    $fileWithType['type'] = $normalizedType;

    try {
        if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
            return 'Error: No file uploaded';
        }
        $ttlData = $this->uploadedFileToTtlConverter->extractTurtleFromPhpUpload($fileWithType);

        if ($uploadType) {
            try {
                $this->ttlContentInspectionService->validateUploadType($ttlData, $uploadType);
            } catch (\Exception $e) {
            }
        }

        return $this->uploadTtlData($ttlData, $itemSetId);
    } catch (\Exception $e) {
        return 'Error processing file: ' . $e->getMessage();
    }
}

    

    
/**
 * @param mixed $item
 */
private function isLikelyArrowheadResource($item): bool
{
    $resourceClass = $item->resourceClass();
    if ($resourceClass && strpos(strtolower((string) $resourceClass->label()), 'arrowhead') !== false) {
        return true;
    }

    $values = $item->values();
    $arrowheadProperties = [
        'Arrowhead Shape', 'Arrowhead Variant', 'Arrowhead Base',
        'Chipping Mode', 'Chipping Direction', 'Chipping Shape',
    ];

    foreach ($arrowheadProperties as $property) {
        if (isset($values[$property]) && !empty($values[$property])) {
            return true;
        }
    }

    return false;
}

/**
 * Uploads TTL data to the specified item set.
 *
 * @param mixed $itemSetId
 */
private function uploadTtlData(string $ttlData, $itemSetId = null): string
{
    $id = ($itemSetId !== null && $itemSetId !== '') ? (int) $itemSetId : null;

    $this->currentProcessingItemSetId = $id;
    try {
        return $this->ttlUploadOrchestrationService->uploadTtlData(
            $ttlData,
            $id,
            $this->uploadedFiles,
            $this->excavationData
        );
    } finally {
        $this->currentProcessingItemSetId = null;
    }
}







}
