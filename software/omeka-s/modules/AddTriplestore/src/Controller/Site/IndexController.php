<?php

namespace AddTriplestore\Controller\Site;

require 'vendor/autoload.php';

use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\Ingestion\CollectingFormToArrowheadMapper;
use AddTriplestore\Service\Ingestion\OmekaIngestionService;
use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\Ingestion\OmekaRestSubmissionService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\Ttl\ArrowheadTtlBuilder;
use AddTriplestore\Service\Ttl\ExcavationTtlBuilder;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use AddTriplestore\Service\Ttl\TtlUriNormalizer;
use AddTriplestore\Service\Ttl\XmlToTtlPipeline;
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
        CollectingFormToArrowheadMapper $collectingFormToArrowheadMapper
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
                    $result = $this->createSiteOnlyUser($validatedData);
                    
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

                    $propertyLabel = $this->getHumanReadableLabel($term);
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
            $excavationData = $this->transformCollectingFormToExcavationData($formData);
            
            if (!empty($excavationData)) {

                $excavationIdentifier = $excavationData['excavation_id'] ?? null;
                
                
                // ttlData processing
                $ttlData = $this->excavationTtlBuilder->buildFromFormData($excavationData, $excavationIdentifier);
                $itemSetData = $this->createExcavationItemSetData($excavationIdentifier, $excavationData);
                
                try {
                    // Create the item set
                    $response = $this->api()->create('item_sets', $itemSetData);
                    if ($response) {
                        $newItemSet = $response->getContent();
                        $itemSetId = $newItemSet->id();
                        
                        // Store the mapping between item set and excavation
                        $this->excavationItemSetContextService->storeMappingBetweenItemSetAndExcavation($itemSetId, $excavationIdentifier);
                        
                        // Upload TTL data to triplestore
                        $result = $this->uploadTtlData($ttlData, $itemSetId);
                        
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
                // For item sets, query the GraphDB for TTL data
                $ttlData = $this->queryCompleteExcavationFromGraphDB($id, $resource);
            } else {
                // For items, query the GraphDB for TTL data
                $ttlData = $this->queryItemFromGraphDB($resource, $id);
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
        $archaeologistOptions = $this->getArchaeologistOptions();
        $countryOptions = $this->getCountryOptions();
        $districtOptions = $this->getDistrictOptions();
        $parishOptions = $this->getParishOptions();
        
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


    // ================== USER MANAGEMENT METHODS ==================



    /**
     * Create a site-only user with 'guest' role.
     *
     * @param array $userData ['email', 'name', 'password']
     * @return array ['success' => bool, 'error' => string|null]
     */
    private function createSiteOnlyUser($userData)
    {
        try {
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            
            // Check if user exists
            $checkSql = "SELECT id FROM user WHERE email = ?";
            $stmt = $connection->prepare($checkSql);
            $stmt->execute([$userData['email']]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'A user with this email already exists'];
            }
            
            // Hash password using Omeka method 
            $hashedPassword = $this->getServiceLocator()
                ->get('Omeka\EntityManager')
                ->getRepository('Omeka\Entity\User')
                ->hashPassword($userData['password']);
            
            // Insert user with 'guest' role (no admin access)
            $insertSql = "INSERT INTO user (email, name, role, is_active, password_hash, created) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $connection->prepare($insertSql);
            
            $result = $stmt->execute([
                $userData['email'],
                $userData['name'],
                'guest', // role
                1, // is_active
                $hashedPassword,
                date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $userId = $connection->lastInsertId();
                
                $this->addUserToSite($userId, $this->currentSite()->id());
                
    
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to create user account'];
            }
            
        } catch (\Exception $e) {
    
    
            
            if (strpos($e->getMessage(), 'email') !== false || strpos($e->getMessage(), 'Duplicate') !== false) {
                return ['success' => false, 'error' => 'A user with this email already exists'];
            } else {
                return ['success' => false, 'error' => 'Failed to create account: ' . $e->getMessage()];
            }
        }
    }

    /**
     * Adds a user to the current site
     *
     * @param int $userId The ID of the user to add
     * @param int $siteId The ID of the site to which the user is being added
     */
    private function addUserToSite($userId, $siteId)
    {
        try {
            $connection = $this->getServiceLocator()->get('Omeka\Connection');
            
            // Add user to site_permission table with 'viewer' role
            $insertSql = "INSERT INTO site_permission (site_id, user_id, role) VALUES (?, ?, ?)";
            $stmt = $connection->prepare($insertSql);
            $stmt->execute([$siteId, $userId, 'viewer']);
            
    
            
        } catch (\Exception $e) {
        }
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
 * Processes archaeologist data from the form submission.
 *
 * This method handles both existing and new archaeologists:
 * - For existing archaeologists, it retrieves their data from Omeka.
 * - For new archaeologists, it collects the provided name, ORCID, and email.
 *
 * @param array $formData The form data submitted by the user
 * @return array Processed archaeologist data .
 */
private function processArchaeologistDataFromForm($formData)
{
    $archaeologistData = [
        'existing' => false,
        'name' => null,
        'orcid' => null,
        'email' => null
    ];
    
    // Check if existing archaeologist was selected
    if (isset($formData['existing_archaeologist']) && !empty($formData['existing_archaeologist'])) {
        $archaeologistData['existing'] = true;
        $archaeologistData['item_id'] = $formData['existing_archaeologist'];
        
        // Get the archaeologist data from Omeka
        try {
            $archaeologist = $this->api()->read('items', $formData['existing_archaeologist'])->getContent();
            $values = $archaeologist->values();
            
            // Extract name, ORCID, and email from the archaeologist item
            foreach ($values as $term => $propertyValues) {
                if (!empty($propertyValues) && isset($propertyValues[0])) {
                    $property = $propertyValues[0]->property();
                    if ($property) {
                        $label = $property->label();
                        $value = $propertyValues[0]->value();
                        
                        if (stripos($label, 'name') !== false) {
                            $archaeologistData['name'] = $value;
                        } elseif (stripos($label, 'orcid') !== false || stripos($label, 'account') !== false) {
                            $archaeologistData['orcid'] = str_replace('https://orcid.org/', '', $value);
                        } elseif (stripos($label, 'email') !== false || stripos($label, 'mbox') !== false) {
                            $archaeologistData['email'] = str_replace('mailto:', '', $value);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
   
        }
    } else {
        $archaeologistData['existing'] = false;
        $archaeologistData['name'] = $formData['new_archaeologist_name'] ?? null;
        $archaeologistData['orcid'] = $formData['new_archaeologist_orcid'] ?? null;
        $archaeologistData['email'] = $formData['new_archaeologist_email'] ?? null;
    }
    
    return $archaeologistData;
}

/**
 * Normalize URIs in TTL data for local use.
 *
 * @param string $ttlData The Turtle data containing URIs to normalize
 * @param mixed $itemSetId The ID of the item set to use for normalization
 * @return string The modified Turtle data with normalized URIs
 */
private function normalizeUris($ttlData, $itemSetId)
{
    return $this->ttlUriNormalizer->normalizeUris(
        $ttlData,
        $itemSetId,
        function ($setId) {
            return $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($setId);
        }
    );
}

/**
 * Creates an item set data array for an excavation.
 * @param string $excavationIdentifier The identifier for the excavation
 * @param array $excavationData The excavation data containing relevant information
 * @return array The created item set data array
 */
private function createExcavationItemSetData($excavationIdentifier, $excavationData)
{
    $title = "Excavation $excavationIdentifier";
    $description = "Archaeological excavation";
    
    if (!empty($excavationData['site_name'])) {
        $description .= " at " . $excavationData['site_name'];
    }
    
    if (!empty($excavationData['location'])) {
        $description .= " - " . $excavationData['location'];
    }
    
    $itemSetData = [
        'dcterms:title' => [
            [
                'type' => 'literal',
                'property_id' => 1,
                '@value' => $title
            ]
        ],
        'dcterms:description' => [
            [
                'type' => 'literal',
                'property_id' => 4,
                '@value' => $description
            ]
        ],
        'dcterms:identifier' => [
            [
                'type' => 'literal',
                'property_id' => 10,
                '@value' => $excavationIdentifier
            ]
        ],
        'o:is_public' => true
    ];
    
    // Add creator if archaeologist is available
    if (!empty($excavationData['archaeologist']['name'])) {
        $itemSetData['dcterms:creator'] = [
            [
                'type' => 'literal',
                'property_id' => 7665,
                '@value' => $excavationData['archaeologist']['name']
            ]
        ];
    }
    
    return $itemSetData;
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

    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileType = $file['type'];

    if (strtolower($fileExtension) === 'ttl' && $fileType !== 'application/x-turtle') {
        $fileType = 'application/x-turtle';
    }

    if (!in_array($fileType, ['application/x-turtle', 'application/xml', 'text/xml'])) {
        return 'Invalid file type. Please upload a valid .ttl or .xml file.';
    }

   
    try {
        if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
   
        return 'Error: No file uploaded';
    }
        if ($fileType === 'application/xml' || $fileType === 'text/xml') {
            $rdfXmlData = $this->xmlToTtlPipeline->parseUploadedXmlToRdfXml($file);
            if (is_string($rdfXmlData) && strpos($rdfXmlData, '<?xml') !== false) {
                $ttlData = $this->xmlToTtlPipeline->rdfXmlToTurtle($rdfXmlData);
            } else {
                throw new \Exception('Failed to process XML file: ' . (is_string($rdfXmlData) ? $rdfXmlData : 'invalid response'));
            }
        } else {
            $ttlData = file_get_contents($file['tmp_name']);
        }

   
   

        
        if ($uploadType) {
            try {
                $this->validateUploadType($ttlData, $uploadType);
   


            } catch (\Exception $e) {
                
            }
        }
   
   

        $result = $this->uploadTtlData($ttlData, $itemSetId);
        return $result;
    } catch (\Exception $e) {
        return 'Error processing file: ' . $e->getMessage();
    }
}

    

/**
 * Uploads TTL data to the specified item set.
 * This method handles excavation data, normalizes URIs, and validates context relationships.
 * @param string $ttlData The TTL data to upload
 * @param int|null $itemSetId The ID of the item set to upload to
 * @return string Result message indicating success or failure
 */
private function uploadTtlData(string $ttlData, ?int $itemSetId = null): string {
   
    // Set the current processing context
    $this->currentProcessingItemSetId = $itemSetId;
    
    try {
   
        $isExcavation = false;
        $excavationIdentifier = "0"; 

        try {
            $this->validateUploadType($ttlData, 'excavation');
   
            $isExcavation = true;

   

            $extractedId = $this->extractExcavationIdentifier($ttlData);
   
            if ($extractedId) {
                $excavationIdentifier = $extractedId;
   
            } else {
                
            }
        } catch (\Exception $e) {
               if ($itemSetId) {
   
                    $excavationId = $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($itemSetId);
                    if ($excavationId) {
                        $excavationIdentifier = $excavationId;
    
                    }
                }
        }


        // Normalize URIs based on context
        if ($itemSetId) {
   
            $ttlData = $this->normalizeUris($ttlData, $itemSetId);
   
        } elseif ($isExcavation && $excavationIdentifier) {
   
            if ($this->excavationIdentifierExists($excavationIdentifier)) {
                
   
            }
            
            $excavationMetadata = $this->extractExcavationMetadataFromTtl($ttlData);
            
            try {
                // create itemset
                $itemSetTitle = "Excavation $excavationIdentifier";
                $itemSetDescription = $excavationMetadata['location'] ? 
                    "Archaeological excavation at " . $excavationMetadata['location'] : 
                    "Archaeological excavation with identifier $excavationIdentifier";
                
                $response = $this->api()->create('item_sets', [
                    'dcterms:title' => [
                        [
                            'type' => 'literal',
                            'property_id' => 1,
                            '@value' => $itemSetTitle
                        ]
                    ],
                    'dcterms:description' => [
                        [
                            'type' => 'literal',
                            'property_id' => 4,
                            '@value' => $itemSetDescription
                        ]
                    ],
                    'dcterms:creator' => $excavationMetadata['archaeologist'] ? [
                        [
                            'type' => 'literal',
                            'property_id' => 7665,
                            '@value' => $excavationMetadata['archaeologist']
                        ]
                    ] : [],
                    'o:is_public' => true
                ]);
                
                if ($response) {
                    $newItemSet = $response->getContent();
                    $itemSetId = $newItemSet->id();
                    
   
                    
                    $this->currentProcessingItemSetId = $itemSetId;
   
                    
                    $ttlData = $this->normalizeUris($ttlData, $itemSetId);
   
                    
                    // Store the mapping between item set and excavation
                    $this->excavationItemSetContextService->storeMappingBetweenItemSetAndExcavation($itemSetId, $excavationIdentifier);
                }
            } catch (\Exception $e) {
   
                return 'Error: Failed to create excavation item set - ' . $e->getMessage();
            }
        }
   

   

        // deal with encounter validation for arrowheads
        $isArrowhead = strpos($ttlData, 'ah:Arrowhead') !== false || strpos($ttlData, 'excav:Item') !== false;
   
   
   
        if ($isArrowhead && $itemSetId) {
   
   
        
   
   
            $arrowheadContext = $this->extractArrowheadContextFromTtl($ttlData);
   
            
            $validationResult = $this->validateContextRelationships($arrowheadContext, $itemSetId);            // log the validation result
   
            
            if (!$validationResult['valid']) {

                $errorDetails = "\n\nValidation Details:\n" . json_encode($validationResult['details'], JSON_PRETTY_PRINT);
                return 'Validation Error: ' . $validationResult['error'] . $errorDetails;
            }
            
   
            
            $encounterEvent = $this->findOrCreateEncounterEvent($arrowheadContext, $itemSetId);
   
            
            $ttlData = $this->addEncounterEventToTtl($ttlData, $encounterEvent, $itemSetId);
   
   
        }

        error_log('ttldata: ' . $ttlData, 3, OMEKA_PATH . '/logs/normalizeeeee_uris.log');

        $graphDbResult = $this->sendToGraphDB($ttlData, $itemSetId);
   
        
        
        if (strpos($graphDbResult, 'successfully') !== false) {
            // If GraphDB upload is successful, then process in Omeka S
            $resolvedExcavationId = $itemSetId ? $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($itemSetId) : null;
            $omekaData = $this->omekaIngestionService->transformTtlToOmekaItemPayloads(
                $ttlData,
                $itemSetId,
                $resolvedExcavationId
            );

            $itemSetIdInt = $itemSetId !== null && $itemSetId !== '' ? (int) $itemSetId : null;
            $omekaResponse = $this->omekaRestSubmissionService->submitItemPayloads(
                $omekaData,
                $itemSetIdInt,
                $this->uploadedFiles,
                $this->excavationData
            );
            
            if (empty($omekaResponse['errors'])) {
                $createdItems = $omekaResponse['created_items'];
                $updatedCount = 0;
                
                foreach ($createdItems as $item) {
                    if (is_array($item) && isset($item['o:id'])) {
                        $itemId = $item['o:id']; // Get the Omeka assigned IdD
                    }
                    else {
   
                        $itemId = null;
                    }
                    
                    if ($isExcavation) {
                        $title = "Excavation $excavationIdentifier Item $itemId";
                    } else {
                        $title = "Arrowhead $itemId" . ($excavationIdentifier ? " (Excavation $excavationIdentifier)" : "");
                    }
                    
                    try {
                        $updateResult = $this->api()->update('items', $itemId, [
                            'dcterms:title' => [
                                [
                                    'type' => 'literal',
                                    'property_id' => 1,
                                    '@value' => $title
                                ]
                            ]
                        ], [], ['isPartial' => true]);
                        
                        if ($updateResult) {
                            $updatedCount++;
   
                        }
                    } catch (\Exception $e) {
   
                    }
                }
                
                if ($isExcavation && $itemSetId) {
                    return "Data uploaded successfully to both GraphDB and Omeka S. Created Item Set #{$itemSetId} for excavation '$excavationIdentifier' and " . 
                          count($createdItems) . " items with updated titles.";
                } else {
                    return 'Data uploaded successfully to both GraphDB and Omeka S. Created ' . 
                          count($createdItems) . ' items with updated titles and proper resource links within excavation context.';
                }
            } else {
                return 'Data uploaded to GraphDB, but Omeka S errors: ' . 
                      implode('; ', $omekaResponse['errors']);
            }
        } else {
            return 'Failed to upload data to GraphDB: ' . $graphDbResult;
        }
        
    } finally {
        $this->currentProcessingItemSetId = null;
   
    }
}


/**
 * This method extracts the excavation data from the ttl file
 * @param mixed $ttlData
 * @return array|array{archaeologist: null, location: null}
 */
private function extractExcavationMetadataFromTtl($ttlData) {
    $metadata = [
        'location' => null,
        'archaeologist' => null
    ];
    
    // Extract location name
    if (preg_match('/dbo:informationName\s+"([^"]+)"/i', $ttlData, $matches)) {
        $metadata['location'] = $matches[1];
    }
    
    // Extract archaeologist name  
    if (preg_match('/foaf:name\s+"([^"]+)"/i', $ttlData, $matches)) {
        $metadata['archaeologist'] = $matches[1];
    }
    
    return $metadata;
}


/**
 * Checks if the excavation identifier already exists in the system.
 * @param string $excavationIdentifier The excavation identifier to check
 * @return bool True if the identifier exists, false otherwise
 */
private function excavationIdentifierExists($excavationIdentifier) {
    if (empty($excavationIdentifier)) {
        return false;
    }
    
    try {
        $response = $this->api()->search('items', [
            'property' => [
                [
                    'property' => 10, 
                    'type' => 'eq',
                    'text' => $excavationIdentifier
                ]
            ]
        ]);
        
        return $response->getTotalResults() > 0;
        
    } catch (\Exception $e) {
   
        return false;
    }
}

/** Extracts the excavation identifier from the TTL data.
 * This method looks for both dct:identifier and dcterms:identifier patterns.
 * @param string $ttlData The TTL data to search
 * @return string|null The extracted excavation identifier or null if not found
 */
private function extractExcavationIdentifier(string $ttlData): ?string {
   
    
    if (preg_match('/dct:identifier\s+"([^"]+)"\^\^xsd:literal/', $ttlData, $matches)) {
   
        return $matches[1];
    }
    
    // Alternative dcterms:identifier
    if (preg_match('/dcterms:identifier\s+"([^"]+)"/', $ttlData, $matches)) {
   
        return $matches[1];
    }
    return null;
}


/**
 * Validates the upload type against the content of the TTL data.
 * This method checks if the TTL data contains patterns specific to excavations or arrowheads.
 * @param string $ttlData The TTL data to validate
 * @param string|null $uploadType The type of upload (excavation or arrowhead)
 * @throws \Exception If the validation fails
 */
private function validateUploadType(string $ttlData, ?string $uploadType): void
{
    if (!$uploadType) {
        return; 
    }

   
   
    
    $excavationPatterns = [
        'a excav:Excavation',
        'excav:Excavation',
        'crmarchaeo:A9_Archaeological_Excavation',
        'a crmarchaeo:A9_Archaeological_Excavation',
        'excav:hasPersonInCharge',
        'excav:hasSquare',
        'excav:hasContext'
    ];
    
    $arrowheadPatterns = [
        'a ah:Arrowhead',
        'ah:Arrowhead',
        'a excav:Item',
        'excav:Item',
        'ah:shape',
        'ah:variant',
        'ah:hasMorphology',
        'ah:hasChipping'
    ];
    
    $isExcavation = false;
    foreach ($excavationPatterns as $pattern) {
        if (strpos($ttlData, $pattern) !== false) {
            $isExcavation = true;
   
            break;
        }
    }
    
    $isArrowhead = false;
    foreach ($arrowheadPatterns as $pattern) {
        if (strpos($ttlData, $pattern) !== false) {
            $isArrowhead = true;
   
            break;
        }
    }
    
    
    if ($uploadType === 'excavation' && !$isExcavation) {
        if ($isArrowhead) {
   
            return; 
        }
        throw new \Exception('Invalid data type for excavation upload.');
    } elseif ($uploadType === 'arrowhead' && !$isArrowhead) {
        throw new \Exception('Invalid data type for Arrowhead upload.');
    }
    
   
}

/**
 * Parses the uploaded XML file and applies the appropriate XSLT transformation.
 * This method detects the type of XML (Arrowhead or Excavation) and applies the corresponding XSLT.
 * @param array $file The uploaded XML file
 * @return string|false The transformed RDF-XML data or an error message
 */
private function sendToGraphDB($data, $excavationId)
{
    $this->excavationIdentifier = $excavationId ?: '0';
    $graphUri = $this->baseDataGraphUri . $this->excavationIdentifier . '/';

    return $this->graphDbHttpService->uploadAfterShaclValidation($graphUri, $data);
}



/**
 * Extracts the identifier from the RDF data for a given subject.
 * This method looks for the 'dcterms:identifier' property and returns its value.
 * @param mixed $rdfData The RDF data array
 * @param mixed $subject The subject URI to extract the identifier from
 * @return string|null The identifier value or null if not found
 */
private function extractArrowheadContextFromTtl($ttlData) {
    $context = [
        'excavation' => null,
        'location' => null,
        'square' => null,
        'context' => null,
        'svu' => null,
        'date' => null,
        'item_identifier' => null
    ];
    
   
   
    
    // Extract item identifier
    if (preg_match('/dct:identifier\s+"([^"]+)"/i', $ttlData, $matches)) {
        $context['item_identifier'] = $matches[1];
   
    }

    // Extract date
    if (preg_match('/dct:date\s+"([^"]+)"/i', $ttlData, $matches)) {
        $context['date'] = $matches[1];
   
    }
    
    // IMPROVED: Extract context reference with better pattern matching
    if (preg_match('/excav:foundInContext\s+<([^>]+)>/i', $ttlData, $matches)) {
        $contextUri = $matches[1];
   
        
        // Extract the context identifier from declaration
        if (preg_match('/<' . preg_quote($contextUri, '/') . '>\s+a\s+excav:Context\s*;\s*dct:identifier\s+"([^"]+)"(?:\^\^xsd:literal)?/i', $ttlData, $idMatches)) {
            $context['context'] = $idMatches[1];
   
        } else {
            // extract from URI structure 
            if (preg_match('/\/context\/([^\/\s>]+)/', $contextUri, $contextMatches)) {
                $context['context'] = $contextMatches[1];
   
            }
        }
    }
    
    if (!$context['context']) {
        if (preg_match('/<[^>]*\/context\/([^>\/\s]+)>\s+a\s+excav:Context/i', $ttlData, $matches)) {
            $context['context'] = $matches[1];
   
        }
    }
    
    if (preg_match('/excav:foundInSVU\s+<([^>]+)>/i', $ttlData, $matches)) {
        $svuUri = $matches[1];
   
        
        if (preg_match('/<' . preg_quote($svuUri, '/') . '>\s+a\s+excav:StratigraphicVolumeUnit\s*;\s*dct:identifier\s+"([^"]+)"(?:\^\^xsd:literal)?/i', $ttlData, $idMatches)) {
            $context['svu'] = $idMatches[1];
   
        } else {
            if (preg_match('/\/svu\/([^\/\s>]+)/', $svuUri, $svuMatches)) {
                $context['svu'] = $svuMatches[1];
   
            }
        }
    }
    
    if (!$context['svu']) {
        if (preg_match('/<[^>]*\/svu\/([^>\/\s]+)>\s+a\s+excav:StratigraphicVolumeUnit/i', $ttlData, $matches)) {
            $context['svu'] = $matches[1];
   
        }
    }


    
    if (preg_match('/excav:foundInLocation\s+<([^>]+)>/i', $ttlData, $matches)) {
        $locationUri = $matches[1];
   
        
        if (preg_match('/<' . preg_quote($locationUri, '/') . '>\s+a\s+excav:Location\s*;\s*dbo:informationName\s+"([^"]+)"/i', $ttlData, $nameMatches)) {
            $context['location'] = $this->ttlUriHelper->createUrlSlug($nameMatches[1]);
   
        } else {
            if (preg_match('/\/location\/([^\/]+)$/', $locationUri, $locationMatches)) {
                $context['location'] = $locationMatches[1];
   
            }
        }
    }
    
    if (preg_match('/excav:foundInExcavation\s+<([^>]+)>/i', $ttlData, $matches)) {
        $excavationUri = $matches[1];
   
        
        if (preg_match('/<' . preg_quote($excavationUri, '/') . '>\s+a\s+excav:Excavation\s*;\s*dct:identifier\s+"([^"]+)"/i', $ttlData, $idMatches)) {
            $context['excavation'] = $idMatches[1];
   
        } else {
            if (preg_match('/\/excavation\/([^\/]+)(?:\/|$)/', $excavationUri, $excavationMatches)) {
                $context['excavation'] = $excavationMatches[1];
   
            }
        }
    }
    
    if (!$context['excavation'] && preg_match('/encounter\/encounter-(\d+)/', $ttlData, $matches)) {
        if (preg_match('/excav:EncounterEvent\s*;\s*.*?excav:foundInExcavation\s+<([^>]+)>/s', $ttlData, $encMatches)) {
            if (preg_match('/\/excavation\/([^\/]+)(?:\/|$)/', $encMatches[1], $excavationMatches)) {
                $context['excavation'] = $excavationMatches[1];
   
            }
        }
    }

    if (preg_match('/excav:foundInSquare\s+<([^>]+)>/i', $ttlData, $matches)) {
        $squareUri = $matches[1];
        if (preg_match('/\/square\/([^\/\s>]+)/', $squareUri, $squareMatches)) {
            $context['square'] = $squareMatches[1];
   
        }
    } 
    
    $hasValidContext = $context['excavation'] || $context['location'] || $context['context'] || $context['svu'] || $context['square'];
    
    
   
    
    return $context;
}

/**
 * Validates the context relationships for an arrowhead item.
 * This method checks if the context and SVU exist in the excavation and if their relationship is valid.
 * @param array $arrowheadContext The context data extracted from the arrowhead
 * @param int $itemSetId The ID of the item set to validate against
 * @return array An array containing validation results, errors, and details
 */
private function validateContextRelationships($arrowheadContext, $itemSetId) {
   
    
    $excavationRelationships = $this->getExcavationRelationshipsFromGraphDB($itemSetId);
   
    $errors = [];
    $details = [];

   
    
    if ($arrowheadContext['svu']) {
   
        if (strpos($arrowheadContext['svu'], '/') !== false) {
            $parts = explode('/', rtrim($arrowheadContext['svu'], '/'));
            $arrowheadContext['svu'] = end($parts);
        }
        
        if (!in_array($arrowheadContext['svu'], $excavationRelationships['svus'])) {
            $errors[] = "SVU '{$arrowheadContext['svu']}' does not exist in this excavation";
            $details['available_svus'] = $excavationRelationships['svus'];
        }
    }
    
    if ($arrowheadContext['context']) {
        if (strpos($arrowheadContext['context'], '/') !== false) {
            $parts = explode('/', rtrim($arrowheadContext['context'], '/'));
            $arrowheadContext['context'] = end($parts);
        }
        
        if (!in_array($arrowheadContext['context'], $excavationRelationships['contexts'])) {
            $errors[] = "Context '{$arrowheadContext['context']}' does not exist in this excavation";
            $details['available_contexts'] = $excavationRelationships['contexts'];
        }
    }
    
    if ($arrowheadContext['context'] && $arrowheadContext['svu']) {
        $relationshipExists = false;
        foreach ($excavationRelationships['context_svu_links'] as $link) {
            if ($link['context'] === $arrowheadContext['context'] && 
                $link['svu'] === $arrowheadContext['svu']) {
                $relationshipExists = true;
                break;
            }
        }
        
        if (!$relationshipExists) {
            $errors[] = "Invalid relationship: Context '{$arrowheadContext['context']}' is not linked to SVU '{$arrowheadContext['svu']}' in this excavation";
            $details['valid_relationships'] = $excavationRelationships['context_svu_links'];
        }
    }
    
   
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'details' => $details
    ];
}

/**
 * Retrieves excavation relationships from the GraphDB for a given item set ID.
 * This method queries the GraphDB to find contexts and SVUs related to the item set.
 * @param int $itemSetId The ID of the item set to query
 * @return array An array containing contexts, SVUs, and context-SVU links
 */
private function getExcavationRelationshipsFromGraphDB($itemSetId) {
    $graphUri = $this->baseDataGraphUri . $itemSetId . "/";
    $query = "
    PREFIX excav: <https://purl.org/megalod/ms/excavation/>
    PREFIX dct: <http://purl.org/dc/terms/>
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>

    SELECT DISTINCT ?contextId ?svuId ?hasRelationship
    WHERE {
        GRAPH <$graphUri> {
            {
                ?context a excav:Context ;
                        dct:identifier ?contextId .
                OPTIONAL {
                    ?context excav:hasSVU ?svu .
                    ?svu dct:identifier ?svuId .
                    BIND(true AS ?hasRelationship)
                }
            }
            UNION
            {
                ?svu a excav:StratigraphicVolumeUnit ;
                     dct:identifier ?svuId .
            }
            UNION
            {
                # Also check public URIs format
                ?context excav:hasSVU ?publicSvu .
                BIND(REPLACE(str(?publicSvu), '.*/([^/]+)$', '$1') as ?svuId)
                BIND(true AS ?hasRelationship)
            }
        }
    }";

    try {
        $results = $this->executeGraphDbQuery($query);
        
        if (!empty($results) && isset($results['results']['bindings'])) {
            $contexts = [];
            $svus = [];
            $contextSvuLinks = [];
            
            foreach ($results['results']['bindings'] as $binding) {
                if (isset($binding['contextId'])) {
                    $contexts[] = $binding['contextId']['value'];
                }
                if (isset($binding['svuId'])) {
                    $svus[] = $binding['svuId']['value'];
                }
                if (isset($binding['hasRelationship']) && 
                    isset($binding['contextId']) && 
                    isset($binding['svuId'])) {
                    $contextSvuLinks[] = [
                        'context' => $binding['contextId']['value'],
                        'svu' => $binding['svuId']['value']
                    ];
                }
            }
            
            return [
                'contexts' => array_unique($contexts),
                'svus' => array_unique($svus),
                'context_svu_links' => $contextSvuLinks
            ];
        }
    } catch (\Exception $e) {
   
    }
    
    return [
        'contexts' => [],
        'svus' => [],
        'context_svu_links' => []
    ];
}

/**
 * Finds or creates an encounter event based on the arrowhead context.
 * This method checks if an encounter event already exists for the given context and item set.
 * If not, it creates a new encounter event.
 * @param array $arrowheadContext The context data extracted from the arrowhead
 * @param int $itemSetId The ID of the item set to create or find the encounter event in
 * @return array|null The encounter event data or null if not found or created
 */
private function findOrCreateEncounterEvent($arrowheadContext, $itemSetId) {
   
    
    $encounterSignature = $this->generateEncounterSignature($arrowheadContext);
    
    // Check if encounter event already exists
    $existingEncounter = $this->findExistingEncounterEvent($encounterSignature, $itemSetId);
    
    if ($existingEncounter) {
   
        return $existingEncounter;
    }
    
    // Create new encounter event
    $newEncounter = $this->createNewEncounterEvent($arrowheadContext, $itemSetId, $encounterSignature);
   
    
    return $newEncounter;
}

/**
 * Generates a unique signature for the encounter based on the context.
 * This method creates a hash signature that uniquely identifies the encounter event.
 * @param array $context The context data for the encounter
 * @return string The generated MD5 signature
 */
private function generateEncounterSignature($context) {
    $signature = [
        'excavation' => $context['excavation'] ?: 'unknown',
        'context' => $context['context'] ?: 'no-context',
        'svu' => $context['svu'] ?: 'no-svu',
        'date' => $context['date'],
        'square' => $context['square'] ?: 'no-square'
    ];
    
    return md5(json_encode($signature));
}

/**
 * Finds an existing encounter event by signature or title.
 * This method searches for an encounter event in the specified item set that matches the given signature.
 * If no match is found by signature, it tries to find by title.
 * @param string $signature The MD5 signature of the encounter
 * @param int $itemSetId The ID of the item set to search in
 * @return array|null The encounter event data or null if not found
 */
private function findExistingEncounterEvent($signature, $itemSetId) {
    try {
        $searchParams = [
            'resource_class_id' => 123,
            'item_set_id' => $itemSetId,
            'property' => [
                [
                    'property' => 10,
                    'type' => 'eq',
                    'text' => $signature
                ]
            ]
        ];
        
        $response = $this->api()->search('items', $searchParams);
        $encounters = $response->getContent();
        
        if (!empty($encounters)) {
            $encounter = $encounters[0];
   
            return [
                'id' => $encounter->id(),
                'signature' => $signature,
                'omeka_id' => $encounter->id()
            ];
        }
        
        $title = $this->generateEncounterTitle($this->contextFromSignature($signature));
        
        $titleSearchParams = [
            'resource_class_id' => 123,
            'item_set_id' => $itemSetId,
            'property' => [
                [
                    'property' => 1, 
                    'type' => 'eq',
                    'text' => $title
                ]
            ]
        ];
        
        $response = $this->api()->search('items', $titleSearchParams);
        $encounters = $response->getContent();
        
        if (!empty($encounters)) {
            $encounter = $encounters[0];
   
            return [
                'id' => $encounter->id(),
                'signature' => $signature, 
                'omeka_id' => $encounter->id()
            ];
        }
    } catch (\Exception $e) {
   
    }
    
    return null;
}
/**
 * This method attempts to reverse engineer the context from a signature.
 * @param mixed $signature
 * @return array|array{context: string, date: string, excavation: string, square: string, svu: string}
 */
private function contextFromSignature($signature) {
    static $signatureCache = [];
    
    // Return from cache if already processed
    if (isset($signatureCache[$signature])) {
        return $signatureCache[$signature];
    }
    
   
    $context = [
        'excavation' => 'unknown',
        'context' => 'unknown',
        'svu' => 'unknown',
        'date' => date('Y-m-d'),
        'square' => 'unknown'
    ];
    
    try {
        $searchParams = [
            'property' => [
                [
                    'property' => 10, 
                    'type' => 'eq',
                    'text' => $signature
                ]
            ]
        ];
        
        $response = $this->api()->search('items', $searchParams);
        $encounters = $response->getContent();
        
        if (!empty($encounters)) {
            $encounter = $encounters[0];
            
            $values = $encounter->values();
            
          
            if (isset($values['excav:foundInExcavation'])) {
                $propertyValues = $values['excav:foundInExcavation'];
                if (is_array($propertyValues)) {
                    foreach ($propertyValues as $valueRepresentation) {
                        if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                            $context['excavation'] = $valueRepresentation->uri();
                        } elseif (method_exists($valueRepresentation, 'value')) {
                            $context['excavation'] = $valueRepresentation->value();
                        }
                        break;
                    }
                }
            }
            
            if (isset($values['excav:foundInContext'])) {
                $propertyValues = $values['excav:foundInContext'];
                if (is_array($propertyValues)) {
                    foreach ($propertyValues as $valueRepresentation) {
                        if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                            $context['context'] = $valueRepresentation->uri();
                        } elseif (method_exists($valueRepresentation, 'value')) {
                            $context['context'] = $valueRepresentation->value();
                        }
                        break;
                    }
                }
            }
            
            if (isset($values['excav:foundInSVU'])) {
                $propertyValues = $values['excav:foundInSVU'];
                if (is_array($propertyValues)) {
                    foreach ($propertyValues as $valueRepresentation) {
                        if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                            $context['svu'] = $valueRepresentation->uri();
                        } elseif (method_exists($valueRepresentation, 'value')) {
                            $context['svu'] = $valueRepresentation->value();
                        }
                        break;
                    }
                }
            }
            
            // Get date
            if (isset($values['dcterms:date'])) {
   
                $propertyValues = $values['dcterms:date'];
   
                
                if (is_array($propertyValues)) {
                    foreach ($propertyValues as $valueRepresentation) {
   
                        if (method_exists($valueRepresentation, 'value')) {
                            $context['date'] = $valueRepresentation->value();
   
                        }
                        break;
                    }
                } else {
                    
                    if (method_exists($propertyValues, 'values')) {
                        $actualValues = $propertyValues->values();
                        foreach ($actualValues as $valueRepresentation) {
                            if (method_exists($valueRepresentation, 'value')) {
                                $context['date'] = $valueRepresentation->value();
                                break;
                            }
                        }
                    }
                }
            }
            
            // Get square
            if (isset($values['excav:foundInSquare'])) {
                $propertyValues = $values['excav:foundInSquare'];
                if (is_array($propertyValues)) {
                    foreach ($propertyValues as $valueRepresentation) {
                        if (method_exists($valueRepresentation, 'uri') && $valueRepresentation->uri()) {
                            $context['square'] = $valueRepresentation->uri();
                        } elseif (method_exists($valueRepresentation, 'value')) {
                            $context['square'] = $valueRepresentation->value();
                        }
                        break;
                    }
                }
            }
        }
    } catch (\Exception $e) {
   
    }
    
    $signatureCache[$signature] = $context;
    
    return $context;
}


/**
 * Creates a new encounter event in the specified item set.
 * This method constructs the encounter data and sends it to the API to create a new item.
 * @param array $context The context data for the encounter
 * @param int $itemSetId The ID of the item set to create the encounter in
 * @param string $signature The MD5 signature of the encounter
 * @return array The created encounter event data
 */
private function createNewEncounterEvent($context, $itemSetId, $signature) {
   
   
    $encounterData = [
        'o:resource_class' => ['o:id' => 123],
        'o:item_set' => [['o:id' => $itemSetId]],
        'dcterms:title' => [
            [
                'type' => 'literal',
                'property_id' => 1,
                '@value' => $this->generateEncounterTitle($context)
            ]
        ],
        'dcterms:description' => [
            [
                'type' => 'literal',
                'property_id' => 4,
                '@value' => $this->generateEncounterDescription($context)
            ]
        ],
        'dcterms:date' => [
            [
                'type' => 'literal',
                'property_id' => 7,
                '@value' => $context['date']
            ]
        ],
        // Store signature for future lookups
        'dcterms:identifier' => [
            [
                'type' => 'literal',
                'property_id' => 10,
                '@value' => $signature
            ]
        ]
    ];
    
    // Add context references
    if ($context['context']) {
        $encounterData['excav:foundInContext'] = [
            [
                'type' => 'literal',
                'property_id' => 7672,
                '@value' => $context['context']
            ]
        ];
    }
    
    if ($context['svu']) {
        $encounterData['excav:foundInSVU'] = [
            [
                'type' => 'literal',
                'property_id' => 7671,
                '@value' => $context['svu']
            ]
        ];
    }
    
    try {
        $response = $this->api()->create('items', $encounterData);
        $encounter = $response->getContent();
        
        return [
            'id' => $encounter->id(),
            'signature' => $signature,
            'omeka_id' => $encounter->id()
        ];
    } catch (\Exception $e) {
   
        throw $e;
    }
}

/**
 * Generates a title for the encounter event based on the context.
 * @param mixed $ttlData
 * @param mixed $encounterEvent
 * @param mixed $itemSetId
 * @return string
 */
private function addEncounterEventToTtl($ttlData, $encounterEvent, $itemSetId) {
    $excavationIdentifier = $this->excavationItemSetContextService->getExcavationIdentifierFromItemSet($itemSetId);
   
    $encounterUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier/encounter/encounter-{$encounterEvent['omeka_id']}";
    
    // Extract item identifier and context from TTL
    $itemIdentifier = $this->extractItemIdentifierFromTtl($ttlData);
   
    $arrowheadContext = $this->extractArrowheadContextFromTtl($ttlData);
    
    $encounterTriple = "    crmsci:O19i_was_object_encountered_through <$encounterUri> ;\n";
    
    $pattern = '/(dct:identifier\s+"[^"]+"\^\^xsd:literal\s*;)(\s*)/';
    $replacement = "$1\n$encounterTriple$2";
    
    $enhancedTtl = preg_replace($pattern, $replacement, $ttlData, 1);
    
    $escapedLocal = preg_quote($this->localBaseUri, '/');
    $selfRefPattern = '/excav:foundInExcavation\s+<' . $escapedLocal . $itemSetId . '\/item\/' . preg_quote($itemIdentifier, '/') . '>\s*;\s*\n/';
    $enhancedTtl = preg_replace($selfRefPattern, '', $enhancedTtl);
    
    $excavationRefPattern = '/excav:foundInExcavation\s+<' . $escapedLocal . $itemSetId . '\/excavation\/[^>]+>\s*;\s*\n/';
    $enhancedTtl = preg_replace($excavationRefPattern, '', $enhancedTtl);
    
    $encounterDefinition = "\n\n# =========== ENCOUNTER EVENT ===========\n\n";
    $encounterDefinition .= "<$encounterUri> a excav:EncounterEvent ;\n";

    // Add date if available
    if (!empty($arrowheadContext['date'])) {
        $encounterDefinition .= "    dct:date \"" . $arrowheadContext['date'] . "\"^^xsd:literal ;\n";
    }

    // Add encountered object reference
    $itemUri = "{$this->localBaseUri}$itemSetId/item/$itemIdentifier";
    $encounterDefinition .= "    crmsci:O19_encountered_object <$itemUri> ;\n";

    // Add excavation reference
    $excavationUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier";
    $encounterDefinition .= "    excav:foundInExcavation <$excavationUri> ;\n";

    // Add location reference
    $locationUri = $this->excavationItemSetContextService->getRealLocationUriFromExcavation($itemSetId);
    if ($locationUri && $this->excavationItemSetContextService->locationHasData($itemSetId)) {
        $encounterDefinition .= "    excav:foundInLocation <$locationUri> ;\n";
    }


    if ($arrowheadContext['context']) {
        $contextUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier/context/{$arrowheadContext['context']}";
        $encounterDefinition .= "    excav:foundInContext <$contextUri> ;\n";
    }

    if ($arrowheadContext['svu']) {
        $svuUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier/svu/{$arrowheadContext['svu']}";
        $encounterDefinition .= "    excav:foundInSVU <$svuUri> ;\n";
    }

    $encounterDefinition = rtrim($encounterDefinition, " ;\n") . " .\n\n";
    /*
    $encounterDefinition .= "\n# =========== CONTEXT ENTITY DECLARATIONS ===========\n\n";
    
    $existingDeclarations = $this->checkExistingDeclarations($enhancedTtl, $itemSetId, $excavationIdentifier);
    
    if (!$existingDeclarations['excavation']) {
        $encounterDefinition .= "<$excavationUri> a excav:Excavation ;\n";
        $encounterDefinition .= "    dct:identifier \"$excavationIdentifier\"^^xsd:literal .\n\n";
    }
    
    if ($arrowheadContext['context'] && !$existingDeclarations['context']) {
        $contextUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier/context/{$arrowheadContext['context']}";
        $encounterDefinition .= "<$contextUri> a excav:Context ;\n";
        $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['context']}\"^^xsd:literal .\n\n";
    }
    
    if ($arrowheadContext['svu'] && !$existingDeclarations['svu']) {
        $svuUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier/svu/{$arrowheadContext['svu']}";
        $encounterDefinition .= "<$svuUri> a excav:StratigraphicVolumeUnit ;\n";
        $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['svu']}\"^^xsd:literal .\n\n";
    }
    
    if ($arrowheadContext['location'] && !$existingDeclarations['location']) {
        $locationUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier/location/{$arrowheadContext['location']}";
        $encounterDefinition .= "<$locationUri> a excav:Location ;\n";
        $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['location']}\"^^xsd:literal .\n\n";
    }
    
    if ($arrowheadContext['square'] && !$existingDeclarations['square']) {
        $squareUri = "{$this->localBaseUri}$itemSetId/excavation/$excavationIdentifier/square/{$arrowheadContext['square']}";
        $encounterDefinition .= "<$squareUri> a excav:Square ;\n";
        $encounterDefinition .= "    dct:identifier \"{$arrowheadContext['square']}\"^^xsd:literal .\n\n";
    }
    
    return $enhancedTtl . $encounterDefinition;*/

    return $enhancedTtl . $encounterDefinition;
}
/**
 * Checks for existing declarations in the TTL data.
 * This method looks for existing context, SVU, square, location, and excavation declarations
 * to avoid duplicates when adding new encounter events.
 * @param string $ttlData The TTL data to check
 * @param int $itemSetId The ID of the item set to check against
 * @param string $excavationIdentifier The excavation identifier to look for
 * @return array An associative array indicating which declarations already exist
 */
private function checkExistingDeclarations($ttlData, $itemSetId, $excavationIdentifier) {
    $escapedLocal  = preg_quote($this->localBaseUri, '/');
    $escapedPublic = preg_quote($this->baseDataGraphUri, '/');
    $base = '(?:' . $escapedLocal . '|' . $escapedPublic . ')';
    $sid  = preg_quote($itemSetId, '/');
    $eid  = preg_quote($excavationIdentifier, '/');

    $existing = [
        'context' => false,
        'svu' => false,
        'square' => false,
        'location' => false,
        'excavation' => false
    ];
    
    if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '>\s+a\s+excav:Excavation/', $ttlData)) {
        $existing['excavation'] = true;
    }
    
    if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/context\/[^>]+>\s+a\s+excav:Context/', $ttlData)) {
        $existing['context'] = true;
    }
    
    if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/svu\/[^>]+>\s+a\s+excav:StratigraphicVolumeUnit/', $ttlData)) {
        $existing['svu'] = true;
    }
    
    if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/square\/[^>]+>\s+a\s+excav:Square/', $ttlData)) {
        $existing['square'] = true;
    }
    
    if (preg_match('/<' . $base . $sid . '\/excavation\/' . $eid . '\/location\/[^>]+>\s+a\s+excav:Location/', $ttlData)) {
        $existing['location'] = true;
    }
    
    return $existing;
}

/**
 * Generates a title for the encounter event based on the context.
 * This method constructs a human-readable title that includes the date, context, and SVU.
 * @param array $context The context data for the encounter
 * @return string The generated title
 */
private function generateEncounterTitle($context) {
    $parts = [];
    $parts[] = "Archaeological Encounter Event ";
    if ($context['date']) {
        $parts[] = $context['date'];
    }
    
    if ($context['context'] && $context['svu']) {
        $parts[] = "Context {$context['context']}, SVU {$context['svu']}";
    } elseif ($context['context']) {
        $parts[] = "Context {$context['context']}";
    } elseif ($context['svu']) {
        $parts[] = "SVU {$context['svu']}";
    }
    
    return implode(' - ', $parts) ?: 'Archaeological Encounter Event';
}

/**
 * Generates a description for the encounter event based on the context.
 * This method constructs a detailed description that includes the date, context, SVU, and square.
 * @param array $context The context data for the encounter
 * @return string The generated description
 */
private function generateEncounterDescription($context) {
    $description = "Archaeological encounter event documenting finds";
    
    if ($context['date']) {
        $description .= " from " . $context['date'];
    }
    
    $contextParts = [];
    if ($context['context']) $contextParts[] = "context {$context['context']}";
    if ($context['svu']) $contextParts[] = "stratigraphic unit {$context['svu']}";
    if ($context['square']) $contextParts[] = "square {$context['square']}";
    
    if (!empty($contextParts)) {
        $description .= " in " . implode(', ', $contextParts);
    }
    
    return $description . ".";
}


/**
 * Extracts the item identifier from the TTL data.
 * This method looks for the dct:identifier property in the TTL data and returns its value.
 * @param string $ttlData The TTL data to extract the identifier from
 * @return string The extracted item identifier or 'unknown-item' if not found
 */
private function extractItemIdentifierFromTtl($ttlData) {
    if (preg_match('/dct:identifier\s+"([^"]+)"/i', $ttlData, $matches)) {
        return $matches[1];
    }
    return 'unknown-item';
}

/**
 * Extracts a meaningful identifier from a resource URI structure.
 * This method handles various URI patterns to extract identifiers for contexts, SVUs, and squares.
 * @param string $resourceUri The resource URI to extract the identifier from
 * @return string|null The extracted identifier or null if not found
 */
private function transformCollectingFormToExcavationData($formData)
{
   

    $excavationData = [];
    
    $fieldMappings = [
        'prompt_32' => 'excavation_id',        // Acronym (excavation identifier)
        'prompt_35' => 'site_name',            // Name of the Location 
        'prompt_34' => 'parish',               // parish of Excavation
        'prompt_97' => 'district',             // district of Excavation
        'prompt_51' => 'country',              // Country of Excavation
        'prompt_39' => 'latitude',             // GPS Latitude
        'prompt_40' => 'longitude',            // GPS Longitude
    ];
    
    // Process the basic form mappings
    foreach ($fieldMappings as $collectingField => $excavationField) {
        if (isset($formData[$collectingField]) && !empty($formData[$collectingField])) {
            $excavationData[$excavationField] = $formData[$collectingField];
        }
    }
    
    // Process archaeologist data
    $excavationData['archaeologist'] = $this->processArchaeologistDataFromForm($formData);
    
    // Process entities data
    if (isset($formData['entities_data']) && !empty($formData['entities_data'])) {
        $entitiesJson = $formData['entities_data'];
   
        
        $entitiesData = json_decode($entitiesJson, true);
        if ($entitiesData) {
            $excavationData['entities'] = $entitiesData;
        }
    }

    
   
    
    return $excavationData;
}

/**
 * This method retrieves the archaeologist options from the RDF data.
 * It queries for archaeologists associated with excavations and returns their names and ORCID IDs.
 * @return array An array of archaeologist options with names and ORCID IDs
 */
private function getArchaeologistOptions()
{
    $query = "
    PREFIX foaf: <http://xmlns.com/foaf/0.1/>
    PREFIX excav: <https://purl.org/megalod/ms/excavation/>
    
    SELECT DISTINCT ?name ?orcid
    WHERE {
        ?excavation a excav:Excavation .
        ?excavation excav:hasPersonInCharge ?archaeologist .
        ?archaeologist foaf:name ?name .
        OPTIONAL { 
            ?archaeologist foaf:account ?orcidUri .
            FILTER(CONTAINS(STR(?orcidUri), 'orcid.org'))
            BIND(REPLACE(STR(?orcidUri), '.*/([0-9X-]+)$', '$1') AS ?orcid)
        }
    }
    ORDER BY ?name
    ";
    
    try {
        $results = $this->executeGraphDbQuery($query);
        
        $archaeologists = [];
        if (!empty($results) && isset($results['results']['bindings'])) {
            foreach ($results['results']['bindings'] as $result) {
                $archaeologists[] = [
                    'name' => $result['name']['value'] ?? '',
                    'orcid' => isset($result['orcid']) ? $result['orcid']['value'] : null
                ];
            }
        }
        
   
        
        return $archaeologists;
    } catch (\Exception $e) {
   
        return [];
    }
}


/**
 * This method retrieves the country options from the RDF data.
 * It queries for countries and returns their names.
 * @return array An array of country names
 */
private function getCountryOptions()
{
    $query = "

    PREFIX dbo: <http://dbpedia.org/ontology/>
PREFIX excav: <https://purl.org/megalod/ms/excavation/>

SELECT DISTINCT ?countryName
WHERE {
  ?location a excav:Location ;
            dbo:Country ?country .
  BIND(REPLACE(STR(?country), 'http://dbpedia.org/resource/', '') AS ?countryName)
}
ORDER BY ?countryName
    ";
    ;
    
    try {
        $results = $this->executeGraphDbQuery($query);
        
        $countries = [];
        if (!empty($results) && isset($results['results']['bindings'])) {
            foreach ($results['results']['bindings'] as $result) {
                if (isset($result['countryName'])) {
                    $countries[] = $result['countryName']['value'];
                }
            }
        }
        
        if (empty($countries)) {
            $countries = [];
        }
        
        // Debug log
   
        
        return $countries;
    } catch (\Exception $e) {
   
        return [];
    }
}

/**
 * This method retrieves the district options from the RDF data.
 * It queries for districts and returns their names.
 * @return array An array of district names
 */
private function getDistrictOptions()
{
    $query = "
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX dbo: <http://dbpedia.org/ontology/>

SELECT DISTINCT ?districtName
WHERE {
  ?district rdf:type dbo:District .
  BIND(REPLACE(STR(?district), 'http://dbpedia.org/resource/', '') AS ?districtName)}
    ";
    
    try {
        $results = $this->executeGraphDbQuery($query);
        
        $districts = [];
        if (!empty($results) && isset($results['results']['bindings'])) {
            foreach ($results['results']['bindings'] as $result) {
                if (isset($result['districtName'])) {
                    $districts[] = $result['districtName']['value'];
                }
            }
        }
        
   
        
        return $districts;
    } catch (\Exception $e) {
   
        return [];
    }
}
/**
 * This method retrieves the parish options from the RDF data.
 * It queries for parishes and returns their names.
 * @return array An array of parish names
 */
private function getParishOptions()
{
    $query = "
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX dbo: <http://dbpedia.org/ontology/>

SELECT DISTINCT ?parishName
WHERE {
  ?parish rdf:type dbo:Parish .
  BIND(REPLACE(STR(?parish), 'http://dbpedia.org/resource/', '') AS ?parishName)}
    ";
    
    try {
        $results = $this->executeGraphDbQuery($query);
        
        $parishes = [];
        if (!empty($results) && isset($results['results']['bindings'])) {
            foreach ($results['results']['bindings'] as $result) {
                if (isset($result['parishName'])) {
                    $parishes[] = $result['parishName']['value'];
                }
            }
        }
        
   
        
        return $parishes;
    } catch (\Exception $e) {
   
        return [];
    }
}


/**
 * this method executes a SPARQL query against the GraphDB endpoint.
 * @param mixed $queryString
 * @return mixed|null
 */
private function executeGraphDbQuery($queryString)
{
    return $this->graphDbHttpService->postSparqlJson($queryString);
}





/**
 * This method converts a term to a human-readable label.
 * It maps common terms to readable labels and formats others.
 * @param string $term The term to convert
 * @return string The human-readable label
 */
private function getHumanReadableLabel($term)
{
    // Common property mappings
    $labelMappings = [
        'dcterms:title' => 'Title',
        'dcterms:identifier' => 'Identifier',
        'dcterms:description' => 'Description',
        'bibo:annotates' => 'Annotations',
        'crm:P44_has_condition' => 'Condition',
        'crm:P2_has_type' => 'Type',
        'crm:P43_has_dimension' => 'Dimension',
        'geo:lat' => 'Latitude',
        'geo:long' => 'Longitude',
        'ah:shape' => 'Shape',
        'ah:variant' => 'Variant',
        'ah:hasMorphology' => 'Morphology',
        'excav:elongationIndex' => 'Elongation Index',
        'excav:thicknessIndex' => 'Thickness Index',
        'schema:height' => 'Height',
        'schema:width' => 'Width',
        'schema:depth' => 'Thickness',
        'schema:weight' => 'Weight',
    ];
    
    if (isset($labelMappings[$term])) {
        return $labelMappings[$term];
    }
    

    $label = $term;
    if (strpos($label, ':') !== false) {
        $parts = explode(':', $label);
        $label = end($parts);
    }
    
    $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);
    
    $label = ucwords($label);
    
    return $label;
}
    



/**
 * This method queries the GraphDB for complete excavation data.
 * @param mixed $itemSetId
 * @param mixed $resource
 * @return string|null
 */
private function queryCompleteExcavationFromGraphDB($itemSetId, $resource)
{
    $graphUri = $this->baseDataGraphUri . $itemSetId . "/";
    
   
    
    $query = "
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
    PREFIX sh: <http://www.w3.org/ns/shacl#>
    PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
    PREFIX dct: <http://purl.org/dc/terms/>
    PREFIX foaf: <http://xmlns.com/foaf/0.1/>
    PREFIX dbo: <http://dbpedia.org/ontology/>
    PREFIX crm: <http://www.cidoc-crm.org/cidoc-crm/>
    PREFIX crmsci: <http://cidoc-crm.org/extensions/crmsci/>
    PREFIX crmarchaeo: <http://www.cidoc-crm.org/extensions/crmarchaeo/>
    PREFIX edm: <http://www.europeana.eu/schemas/edm/>
    PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
    PREFIX time: <http://www.w3.org/2006/time#>
    PREFIX schema: <http://schema.org/>
    PREFIX ah: <https://purl.org/megalod/ms/ah/>
    PREFIX excav: <https://purl.org/megalod/ms/excavation/>
    PREFIX dul: <http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#>
    
    CONSTRUCT {
        ?s ?p ?o .
    }
    WHERE {
        GRAPH <$graphUri> {
            ?s ?p ?o .
        }
    }
    ";
    
    $ttlData = $this->executeConstructQuery($query);
    
    if ($ttlData) {
        $organizedTtl = $this->organizeAndFormatTtl($ttlData, $itemSetId);
        
   
        return $organizedTtl;
    }
    
   
    return null;
}



/**
 * This method parses the TTL data into subjects and their statements.
 * @param mixed $ttlData
 * @return array<array<array|string|null>>
 */
private function parseTtlIntoSubjects($ttlData)
{
    $subjects = [];
    $lines = explode("\n", $ttlData);
    $currentSubject = null;
    $currentStatements = [];
    $inStatement = false;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (empty($trimmedLine) || strpos($trimmedLine, '#') === 0) continue;

        if (preg_match('/^(<[^>]+>)\s+(.+)$/', $trimmedLine, $matches) && !$inStatement) {
            if ($currentSubject && !empty($currentStatements)) {
                $subjects[$currentSubject] = $this->cleanStatements($currentStatements);
            }
            $currentSubject = $matches[1];
            $currentStatements = [trim($matches[2])];
            $inStatement = (substr($trimmedLine, -1) !== '.');
        } else if ($currentSubject) {
            $currentStatements[] = $trimmedLine;
            if (substr($trimmedLine, -1) === '.') {
                $subjects[$currentSubject] = $this->cleanStatements($currentStatements);
                $currentSubject = null;
                $currentStatements = [];
                $inStatement = false;
            } else {
                $inStatement = true;
            }
        }
    }
    if ($currentSubject && !empty($currentStatements)) {
        $subjects[$currentSubject] = $this->cleanStatements($currentStatements);
    }
   
    return $subjects;
}

/**
 * This method cleans up the statements by removing unnecessary whitespace,
 * trailing punctuation, and ensuring consistent formatting.
 * @param array $statements
 * @return array
 */
private function cleanStatements($statements)
{
    $cleaned = [];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        $statement = rtrim($statement, ';.');
        
        $statement = preg_replace('/\s+/', ' ', $statement);
        
        if (!empty($statement)) {
            $cleaned[] = $statement;
        }
    }
    
    return $cleaned;
}
/**
 * This method formats the subject statements into a readable TTL format.
 * It groups predicates and objects, removes empty lines, and formats the output.
 * @param string $subject The subject URI
 * @param array $statements The statements associated with the subject
 * @return string The formatted TTL string for the subject
 */
private function formatSubjectStatements($subject, $statements)
{
    if (empty($statements)) {
        return $subject . " .\n";
    }

    $predicateObjects = [];
    $lastPredicate = null;

    foreach ($statements as $statement) {
        $cleanStatement = trim($statement);
        if (empty($cleanStatement)) continue;
        if (strpos($cleanStatement, 'dct:date') === 0) continue;

        // If line starts with a predicate
        if (preg_match('/^([^\s]+)\s+(.+)$/', $cleanStatement, $matches)) {
            $predicate = $matches[1];
            $object = $matches[2];

            // Filter out foundInSVU and foundInContext for main item
            if (
                ($predicate === 'excav:foundInSVU' || $predicate === 'excav:foundInContext') &&
                preg_match('/a\s+(ah:Arrowhead|excav:Item)/', implode(' ', $statements))
            ) {
                continue;
            }

            $predicateObjects[$predicate][] = $object;
            $lastPredicate = $predicate;
        }
        // If line is just a URI object treat as additional object for previous predicate
        elseif ($lastPredicate && preg_match('/^<[^>]+>$/', $cleanStatement)) {
            $predicateObjects[$lastPredicate][] = $cleanStatement;
        }
    }

    $lines = [];
    foreach ($predicateObjects as $predicate => $objects) {
        // Remove empty and duplicate objects, and filter out empty strings
        $objects = array_filter(array_unique(array_map('trim', $objects)), function($o) {
            return $o !== '' && $o !== ',';
        });
        // Remove any trailing commas from each object
        $objects = array_map(function($o) {
            return rtrim($o, ',');
        }, $objects);
        // Remove any empty objects again after trimming
        $objects = array_filter($objects, function($o) {
            return $o !== '';
        });

        if (count($objects) > 1) {
            $lines[] = "    $predicate " . implode(",\n        ", $objects);
        } elseif (count($objects) === 1) {
            $lines[] = "    $predicate " . reset($objects);
        }
    }

    if (empty($lines)) {
        return $subject . " .\n";
    }

    $ttl = $subject . "\n" . implode(" ;\n", $lines) . " .\n";
    return $ttl;
}
/**
 * This method organizes and formats the raw TTL data into a structured format.
 * It groups statements by resource type and adds appropriate headers.
 * @param string $rawTtlData The raw TTL data as a string
 * @param int $itemSetId The ID of the item set for which the TTL is being organized
 * @return string The organized TTL data
 */
private function organizeAndFormatTtl($rawTtlData, $itemSetId)
{
    $subjects = $this->parseTtlIntoSubjects($rawTtlData);
    
    $organizedTtl = $this->ttlUriHelper->getTtlPrefixes();
    $organizedTtl .= "\n# ========================================================================================\n";
    $organizedTtl .= "# ARCHAEOLOGICAL ITEM DATA - ITEM SET $itemSetId\n";
    $organizedTtl .= "# Downloaded from GraphDB on " . date('Y-m-d H:i:s') . "\n";
    $organizedTtl .= "# Organized by resource type for better readability\n";
    $organizedTtl .= "# ========================================================================================\n\n";
    
    $sections = [
        'excavation' => [
            'title' => 'MAIN EXCAVATION',
            'pattern' => '/a\s+excav:Excavation/'
        ],
        'location' => [
            'title' => 'LOCATION',
            'pattern' => '/a\s+excav:Location/'
        ],
        'gps' => [
            'title' => 'GPS COORDINATES', 
            'pattern' => '/a\s+excav:GPSCoordinates/'
        ],
        'archaeologist' => [
            'title' => 'ARCHAEOLOGIST',
            'pattern' => '/a\s+excav:Archaeologist/'
        ],
        'squares' => [
            'title' => 'EXCAVATION SQUARES',
            'pattern' => '/a\s+excav:Square/'
        ],
        'contexts' => [
            'title' => 'CONTEXTS',
            'pattern' => '/a\s+excav:Context/'
        ],
        'svus' => [
            'title' => 'STRATIGRAPHIC VOLUME UNITS',
            'pattern' => '/a\s+excav:StratigraphicVolumeUnit/'
        ],
        'items' => [
            'title' => 'ARCHAEOLOGICAL ITEMS',
            'pattern' => '/a\s+(ah:Arrowhead|excav:Item)/'
        ],
        'morphology' => [
            'title' => 'MORPHOLOGY',
            'pattern' => '/a\s+ah:Morphology/'
        ],
        'chipping' => [
            'title' => 'CHIPPING',
            'pattern' => '/a\s+ah:Chipping/'
        ],
        'measurements' => [
            'title' => 'MEASUREMENTS',
            'pattern' => '/a\s+(excav:TypometryValue|excav:Weight)/'
        ],
        'coordinates' => [
            'title' => 'COORDINATES',
            'pattern' => '/a\s+excav:Coordinates/'
        ],
        'encounters' => [
            'title' => 'ENCOUNTER EVENTS',
            'pattern' => '/a\s+excav:EncounterEvent/'
        ],
        'timelines' => [
            'title' => 'TIMELINES',
            'pattern' => '/a\s+excav:TimeLine/'
        ],
        'instants' => [
            'title' => 'TIME INSTANTS',
            'pattern' => '/a\s+excav:Instant/'
        ],
        'external' => [
            'title' => 'EXTERNAL REFERENCES',
            'pattern' => '/a\s+(dbo:District|dbo:Parish|dbo:Country)/'
        ]
    ];
    
    // Process each section
    foreach ($sections as $sectionKey => $sectionInfo) {
        $sectionSubjects = $this->findSubjectsByPattern($subjects, $sectionInfo['pattern']);
        
        if (!empty($sectionSubjects)) {
            $organizedTtl .= "# =========== {$sectionInfo['title']} ===========\n\n";
            
            foreach ($sectionSubjects as $subject => $statements) {
                $organizedTtl .= $this->formatSubjectStatements($subject, $statements);
                $organizedTtl .= "\n";
            }
        }
    }
    
    return $organizedTtl;
}
/**
 * This method finds subjects in the TTL data that match a specific pattern.
 * It returns an associative array of subjects and their statements that match the pattern.
 * @param array $subjects The parsed subjects from the TTL data
 * @param string $pattern The regex pattern to match against the subject statements
 * @return array An associative array of matching subjects and their statements
 */
private function findSubjectsByPattern($subjects, $pattern)
{
    $matchingSubjects = [];
    
    foreach ($subjects as $subject => $statements) {
        $allStatements = implode(' ', $statements);
        
        if (preg_match($pattern, $allStatements)) {
            $matchingSubjects[$subject] = $statements;
        }
    }
    
    return $matchingSubjects;
}
/**
 * This method retrieves the TTL prefixes used in the RDF data.
 * It returns a string containing the necessary prefixes for the TTL format.
 * @return string The TTL prefixes
 */
private function cleanExistingPrefixes($ttlData)
{
    $lines = explode("\n", $ttlData);
    $cleanedLines = [];
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        if (!empty($trimmedLine) && strpos($trimmedLine, '@prefix') !== 0) {
            $cleanedLines[] = $line;
        }
    }
    
    return implode("\n", $cleanedLines);
}


/**
 * This method queries the GraphDB for a specific item by its ID.
 * @param mixed $resource
 * @param mixed $itemId
 * @return string|null
 */
private function queryItemFromGraphDB($resource, $itemId)
{
    $itemSetId = null;
    
    $itemSets = $resource->itemSets();
    if (!empty($itemSets)) {
        $firstSet = reset($itemSets);
        if ($firstSet) {
            $itemSetId = $firstSet->id();
        }
    }
    
    if (!$itemSetId) {
   
        return null;
    }
    
    $graphUri = "{$this->baseDataGraphUri}{$itemSetId}/";
    
    $values = $resource->values();
    $identifier = null;
    if (isset($values['dcterms:identifier'])) {
        $identifier = $values['dcterms:identifier']['values'][0]->value();
    }
    
    if (!$identifier) {
   
        return null;
    }
    
    $itemUriPattern = "{$this->localBaseUri}$itemSetId/item/$identifier";
    
    $query = "
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
    PREFIX sh: <http://www.w3.org/ns/shacl#>
    PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
    PREFIX dct: <http://purl.org/dc/terms/>
    PREFIX foaf: <http://xmlns.com/foaf/0.1/>
    PREFIX dbo: <http://dbpedia.org/ontology/>
    PREFIX crm: <http://www.cidoc-crm.org/cidoc-crm/>
    PREFIX crmsci: <http://cidoc-crm.org/extensions/crmsci/>
    PREFIX crmarchaeo: <http://www.cidoc-crm.org/extensions/crmarchaeo/>
    PREFIX edm: <http://www.europeana.eu/schemas/edm/>
    PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
    PREFIX time: <http://www.w3.org/2006/time#>
    PREFIX schema: <http://schema.org/>
    PREFIX ah: <https://purl.org/megalod/ms/ah/>
    PREFIX excav: <https://purl.org/megalod/ms/excavation/>
    PREFIX dul: <http://www.ontologydesignpatterns.org/ont/dul/DUL.owl#>
    
    CONSTRUCT {
        ?s ?p ?o .
        ?related ?relP ?relO .
        ?encounter ?encP ?encO .
    }
    WHERE {
        GRAPH <$graphUri> {
            # Main item and its direct properties
            <$itemUriPattern> ?p ?o .
            BIND(<$itemUriPattern> AS ?s)
            
            # Get related resources (morphology, chipping, coordinates, etc.)
            OPTIONAL {
                <$itemUriPattern> ?linkProp ?related .
                ?related ?relP ?relO .
                FILTER(STRSTARTS(STR(?related), STR(<$itemUriPattern>)))
            }
            
            # Get encounter events that reference this item
            OPTIONAL {
                ?encounter crmsci:O19_encountered_object <$itemUriPattern> .
                ?encounter ?encP ?encO .
            }
            
            # Get context resources (location, square, context, svu)
            OPTIONAL {
                <$itemUriPattern> ?contextProp ?contextRes .
                ?contextRes ?ctxP ?ctxO .
                FILTER(?contextProp IN (excav:foundInLocation, excav:foundInSquare, excav:foundInContext, excav:foundInSVU))
                BIND(?contextRes AS ?s)
                BIND(?ctxP AS ?p)
                BIND(?ctxO AS ?o)
            }
        }
    }
    ";
    
    $rawTtlData = $this->executeConstructQuery($query);

    if ($rawTtlData) {
        $organizedTtl = $this->organizeAndFormatItemTtl($rawTtlData, $identifier, $itemSetId);
        
   
        return $organizedTtl;
    }
    
    return null;
}
/**
 * This method organizes and formats the raw TTL data for a specific item.
 * It groups statements by resource type and adds appropriate headers.
 * @param string $rawTtlData The raw TTL data as a string
 * @param string $identifier The identifier of the item
 * @param int $itemSetId The ID of the item set for which the TTL is being organized
 * @return string The organized TTL data
 */
private function organizeAndFormatItemTtl($rawTtlData, $identifier, $itemSetId)
{
    $subjects = $this->parseTtlIntoSubjects($rawTtlData);
    
    // Build organized TTL
    $organizedTtl = $this->ttlUriHelper->getTtlPrefixes();
    $organizedTtl .= "\n# ========================================================================================\n";
    $organizedTtl .= "# ARCHAEOLOGICAL ITEM DATA - " . strtoupper($identifier) . "\n";
    $organizedTtl .= "# Downloaded from GraphDB on " . date('Y-m-d H:i:s') . "\n";
    $organizedTtl .= "# Item Set: $itemSetId | Item ID: $identifier\n";
    $organizedTtl .= "# Organized by resource type for better readability\n";
    $organizedTtl .= "# ========================================================================================\n\n";
    
    $sections = [
        'main_item' => [
            'title' => 'MAIN ARCHAEOLOGICAL ITEM',
            'pattern' => '/(ah:Arrowhead|excav:Item)/'
        ],
        'morphology' => [
            'title' => 'MORPHOLOGY',
            'pattern' => '/ah:Morphology/'
        ],
        'chipping' => [
            'title' => 'CHIPPING',
            'pattern' => '/ah:Chipping/'
        ],
        'typometry' => [
            'title' => 'TYPOMETRY VALUES',
            'pattern' => '/excav:TypometryValue/'
        ],
        'weights' => [
            'title' => 'WEIGHT VALUES',
            'pattern' => '/excav:Weight/'
        ],
        'coordinates' => [
            'title' => 'COORDINATES IN SQUARE',
            'pattern' => '/excav:Coordinates/'
        ],
        'gps' => [
            'title' => 'GPS COORDINATES',
            'pattern' => '/excav:GPSCoordinates/'
        ],
        'encounters' => [
            'title' => 'ENCOUNTER EVENTS',
            'pattern' => '/excav:EncounterEvent/'
        ],
        'excavation' => [
            'title' => 'EXCAVATION REFERENCE',
            'pattern' => '/excav:Excavation/'
        ],
        'location' => [
            'title' => 'LOCATION REFERENCE',
            'pattern' => '/excav:Location/'
        ],
        'squares' => [
            'title' => 'SQUARE REFERENCE',
            'pattern' => '/excav:Square/'
        ],
        'contexts' => [
            'title' => 'CONTEXT REFERENCE',
            'pattern' => '/excav:Context/'
        ],
        'svus' => [
            'title' => 'SVU REFERENCE',
            'pattern' => '/excav:StratigraphicVolumeUnit/'
        ],
        'timelines' => [
            'title' => 'TIMELINE REFERENCE',
            'pattern' => '/excav:TimeLine/'
        ],
        'instants' => [
            'title' => 'TIME INSTANT REFERENCE',
            'pattern' => '/excav:Instant/'
        ],
        'external' => [
            'title' => 'EXTERNAL REFERENCE DECLARATIONS',
            'pattern' => '/(dbo:district|dbo:parish|dbo:Country)/'
        ]
    ];
    
    // Process each section
    foreach ($sections as $sectionKey => $sectionInfo) {
        $sectionSubjects = $this->findSubjectsByPattern($subjects, $sectionInfo['pattern']);
        
        if (!empty($sectionSubjects)) {
            $organizedTtl .= "# =========== {$sectionInfo['title']} ===========\n\n";
            
            if ($sectionKey === 'main_item') {
                $mainItemUri = "{$this->localBaseUri}$itemSetId/item/$identifier";
                if (isset($sectionSubjects["<$mainItemUri>"])) {
                    $organizedTtl .= $this->formatSubjectStatements("<$mainItemUri>", $sectionSubjects["<$mainItemUri>"]);
                    unset($sectionSubjects["<$mainItemUri>"]);
                    $organizedTtl .= "\n";
                }
            }
            
            foreach ($sectionSubjects as $subject => $statements) {
                $organizedTtl .= $this->formatSubjectStatements($subject, $statements);
                $organizedTtl .= "\n";
            }
            
            $organizedTtl .= "\n";
        }
    }

    
    
    return $organizedTtl;
}
/**
 * This method executes a SPARQL CONSTRUCT query against the GraphDB endpoint.
 * It returns the TTL data as a string, or null if the query fails.
 * @param string $query The SPARQL CONSTRUCT query to execute
 * @return string|null The TTL data or null on failure
 */
private function executeConstructQuery($query)
{
    try {
        $client = new \Laminas\Http\Client();
        $client->setUri($this->graphdbQueryEndpoint);
        $client->setMethod('POST');
        $client->setHeaders([
            'Content-Type' => 'application/sparql-query',
            'Accept' => 'text/turtle' 
        ]);
        $client->setRawBody($query);
        
        $response = $client->send();
        
        if ($response->isSuccess()) {
            $ttlData = $response->getBody();
            
            if (strpos($ttlData, '@prefix') === false) {
                $ttlData = $this->ttlUriHelper->getTtlPrefixes() . "\n" . $ttlData;
            }
            
            return $ttlData;
        } else {
   
            return null;
        }
        
    } catch (\Exception $e) {
   
        return null;
    }
}

/**
 * This method queries the GraphDB using SPARQL and returns the results.
 * It sends a POST request to the GraphDB query endpoint with the provided query.
 * @param string $query The SPARQL query to execute
 * @return array The results of the query as an associative array
 */
private function generateArrowheadTtlWithOriginalUris($resource)
{
    $values = $resource->values();
    
    // Extract the original normalized URI from the context references
    $originalBaseUri = $this->extractOriginalBaseUri($values, $resource);
    if ($originalBaseUri == null) {
   
        return "# No valid excavation context found for resource: " . $resource->id() . "\n";
    }
    $identifier = $this->extractIdentifierFromResource($resource);
    
    // Use the original normalized URI structure
    $arrowheadUri = "$originalBaseUri/item/$identifier";
    
    // Start building TTL with single resource comment
    $ttl = "# Resource: " . $resource->displayTitle() . "\n\n";
    
    // Main arrowhead declaration
    $ttl .= "<$arrowheadUri> a excav:Item, ah:Arrowhead ;\n";
    
    // Add identifier
    if ($identifier) {
        $ttl .= "    dct:identifier \"$identifier\"^^xsd:literal ;\n";
    }
    
    // Add basic properties
    $ttl .= $this->processArrowheadCorePropertiesWithOriginalUris($values, $arrowheadUri, $originalBaseUri);
    
    // Add measurement references
    $ttl .= $this->addMeasurementReferences($values, $arrowheadUri, $identifier);
    
    // Add morphology reference
    if ($this->hasMorphologyData($values)) {
        $ttl .= "    ah:hasMorphology <$arrowheadUri/morphology/$identifier-morphology> ;\n";
    }
    
    // Add chipping reference  
    if ($this->hasChippingData($values)) {
        $ttl .= "    ah:hasChipping <$arrowheadUri/chipping/$identifier-chipping> ;\n";
    }
    
    // Add coordinates reference
    if ($this->hasCoordinatesData($values)) {
        $ttl .= "    excav:hasCoordinatesInSquare <$arrowheadUri/coordinates/$identifier-coordinates> ;\n";
    }
    
    // Add GPS coordinates reference
    if ($this->hasGpsData($values)) {
        $excavationId = $this->extractExcavationId($values);
        $ttl .= "    excav:hasGPSCoordinates <$originalBaseUri/excavation/$excavationId/gps/$identifier-gps> ;\n";
    }
    
    // Close main resource (remove trailing semicolon and add period)
    $ttl = rtrim($ttl, " ;\n") . " .\n\n";
    
    // Now add all the separate objects in order
    $ttl .= $this->processMeasurementsWithOriginalUris($values, $arrowheadUri, $identifier);
    $ttl .= $this->processMorphologyWithOriginalUris($values, $arrowheadUri, $identifier);
    $ttl .= $this->processChippingWithOriginalUris($values, $arrowheadUri, $identifier);
    $ttl .= $this->processCoordinatesWithOriginalUris($values, $arrowheadUri, $identifier);
    $ttl .= $this->processGPSWithOriginalUris($values, $originalBaseUri, $identifier);
    
    return $ttl;
}

/**
 * This method adds measurement references to the TTL string.
 * It checks for various measurement values and constructs the appropriate URIs.
 * @param array $values The values from the resource
 * @param string $arrowheadUri The base URI for the arrowhead
 * @param string $identifier The identifier of the resource
 * @return string The TTL string with measurement references
 */
private function addMeasurementReferences($values, $arrowheadUri, $identifier)
{
    $ttl = "";
    
    $measurements = [
        'Height' => ['height', 'schema:height'],
        'Width' => ['width', 'schema:width'],
        'Weight' => ['weight', 'schema:weight'], 
        'Thickness' => ['depth', 'schema:depth'],
        'Body Length' => ['bodylength', 'ah:hasBodyLength'],
        'Base Length' => ['baselength', 'ah:hasBaseLength']
    ];
    
    foreach ($measurements as $label => $config) {
        $suffix = $config[0];
        $property = $config[1];
        
        if (isset($values[$label]) && !empty($values[$label]['values'])) {
            if ($property === 'schema:weight') {
                $ttl .= "    schema:weight <$arrowheadUri/weight/$identifier-weight> ;\n";
            } elseif (strpos($property, 'ah:') === 0) {
                $ttl .= "    $property <$arrowheadUri/$suffix/$identifier-$suffix> ;\n";
            } else {
                $propertyName = str_replace('schema:', '', $property);
                $ttl .= "    schema:$propertyName <$arrowheadUri/typometry/$identifier-$suffix> ;\n";
            }
        }
    }
    
    return $ttl;
}

/**
 * This method checks if the values contain morphology data.
 * It looks for specific properties that indicate morphology information.
 * @param array $values The values from the resource
 * @return bool True if morphology data is present, false otherwise
 */
private function hasMorphologyData($values)
{
    return isset($values['ah:point']) || isset($values['ah:body']) || isset($values['ah:base']);
}
/**
 * This method processes the chipping data and generates the TTL string.
 * @param mixed $values
 * @return bool
 */
private function hasChippingData($values)
{
    $chippingProperties = ['ah:chippingMode', 'ah:chippingAmplitude', 'ah:chippingDirection', 
                          'ah:chippingOrientation', 'ah:chippingDelineation', 'ah:chippingLocationSide',
                          'ah:chippingLocationTransversal', 'ah:chippingShape'];
    
    foreach ($chippingProperties as $prop) {
        if (isset($values[$prop])) {
            return true;
        }
    }
    return false;
}

/**
 * This method checks if the values contain coordinates data.
 * @param mixed $values
 * @return bool
 */
private function hasGpsData($values)
{
    return isset($values['excavation:hasGPSCoordinates']);
}
/**
 * This method extracts the excavation ID from the values.
 * It looks for the excavation context reference and extracts the ID from the URI.
 * @param mixed $values
 * @param mixed $resource
 * @return string|null The excavation ID or null if not found
 */
private function extractOriginalBaseUri($values, $resource)
{
    $contextProperties = [
        'excavation:foundInLocation',
        'excavation:foundInSquare', 
        'excavation:foundInContext',
        'excavation:foundInSVU'
    ];
    
    foreach ($contextProperties as $property) {
        if (isset($values[$property])) {
            foreach ($values[$property]['values'] as $value) {
                if ($value->uri()) {
                    $uri = $value->uri();
                    $escapedPub = preg_quote($this->baseDataGraphUri, '/');
                    if (preg_match('/^(' . $escapedPub . '\d+)\/excavation\/[^\/]+\//', $uri, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
    }
    
    $itemSets = $resource->itemSets();
    if (!empty($itemSets)) {
        $itemSetId = $itemSets[0]->id();
        return "{$this->localBaseUri}$itemSetId";
    }
    
    return null;
}

/**
 * This method extracts the identifier from the resource.
 * It looks for the dcterms:identifier property and returns its value.
 * If not found, it generates a default identifier based on the resource ID.
 * @param mixed $resource The resource from which to extract the identifier
 * @return string The extracted or generated identifier
 */
private function extractIdentifierFromResource($resource)
{
    $values = $resource->values();
    
    if (isset($values['dcterms:identifier'])) {
        foreach ($values['dcterms:identifier']['values'] as $value) {
            return $value->value();
        }
    }
    
    return 'item-' . $resource->id();
}

/**
 * This method processes the core properties of an arrowhead resource
 * and generates the TTL string using the original URIs.
 * @param mixed $values
 * @param mixed $arrowheadUri
 * @param mixed $originalBaseUri
 * @return string
 */
private function processArrowheadCorePropertiesWithOriginalUris($values, $arrowheadUri, $originalBaseUri)
{
    $ttl = "";
    
    // Process description
    if (isset($values['dcterms:description'])) {
        foreach ($values['dcterms:description']['values'] as $value) {
            $ttl .= "    dbo:Annotation \"" . $this->escapeTtlString($value->value()) . "\"^^xsd:literal ;\n";
        }
    }
    
    // Process condition state as boolean
    if (isset($values['crm:P44_has_condition'])) {
        foreach ($values['crm:P44_has_condition']['values'] as $value) {
            $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
            $ttl .= "    crm:E3_Condition_State $boolValue ;\n";
        }
    }
    
    // Process type as boolean
    if (isset($values['crm:P2_has_type'])) {
        foreach ($values['crm:P2_has_type']['values'] as $value) {
            $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
            $ttl .= "    crm:E55_Type $boolValue ;\n";
        }
    }
    
    // Process material with proper URI
    if (isset($values['schema:material'])) {
        foreach ($values['schema:material']['values'] as $value) {
            $materialUri = "http://vocab.getty.edu/page/aat/" . $value->value();
            $ttl .= "    crm:E57_Material <$materialUri> ;\n";
        }
    }
    
    // Process shape with controlled vocabulary URI (preserve original)
    if (isset($values['ah:shape'])) {
        foreach ($values['ah:shape']['values'] as $value) {
            $shapeValue = strtolower($value->value());
            $ttl .= "    ah:shape <https://purl.org/megalod/kos/ah-shape/$shapeValue> ;\n";
        }
    }
    
    if (isset($values['ah:variant'])) {
        foreach ($values['ah:variant']['values'] as $value) {
            $variantValue = strtolower($value->value());
            $ttl .= "    ah:variant <https://purl.org/megalod/kos/ah-variant/$variantValue> ;\n";
        }
    }
    
    if (isset($values['excavation:elongationIndex'])) {
        foreach ($values['excavation:elongationIndex']['values'] as $value) {
            $ttl .= "    excav:elongationIndex <https://purl.org/megalod/kos/MegaLOD-IndexElongation/" . $value->value() . "> ;\n";
        }
    }
    
    if (isset($values['excavation:thicknessIndex'])) {
        foreach ($values['excavation:thicknessIndex']['values'] as $value) {
            $ttl .= "    excav:thicknessIndex <https://purl.org/megalod/kos/MegaLOD-IndexThickness/" . $value->value() . "> ;\n";
        }
    }
    
    $contextProperties = [
        'excavation:foundInLocation' => 'excav:foundInLocation',
        'excavation:foundInSquare' => 'excav:foundInSquare', 
        'excavation:foundInContext' => 'excav:foundInContext',
        'excavation:foundInSVU' => 'excav:foundInSVU'
    ];
    
    foreach ($contextProperties as $omekaProperty => $ttlProperty) {
        if (isset($values[$omekaProperty])) {
            foreach ($values[$omekaProperty]['values'] as $value) {
                if ($value->uri()) {
                    $ttl .= "    $ttlProperty <" . $value->uri() . "> ;\n";
                }
            }
        }
    }

    if (isset($values['district'])) {
        foreach ($values['district']['values'] as $value) {
            $districtName = $value->value();
            $districtSlug = str_replace(' ', '_', $districtName);
            $districtUri = "http://dbpedia.org/resource/$districtSlug";
            $ttl .= "    dbo:district <$districtUri> ;\n";
            $entitiesToDeclare['district'] = [
                'uri' => $districtUri,
                'name' => $districtName
            ];
        }
    }
    
    if (isset($values['parish'])) {
        foreach ($values['parish']['values'] as $value) {
            $parishName = $value->value();
            $parishSlug = str_replace(' ', '_', $parishName);
            $parishUri = "http://dbpedia.org/resource/$parishSlug";
            $ttl .= "    dbo:parish <$parishUri> ;\n";
            $entitiesToDeclare['parish'] = [
                'uri' => $parishUri,
                'name' => $parishName
            ];
        }
    }
    
    if (isset($values['Country'])) {
        foreach ($values['Country']['values'] as $value) {
            $countryName = $value->value();
            $countrySlug = str_replace(' ', '_', $countryName);
            $countryUri = "http://dbpedia.org/resource/$countrySlug";
            $ttl .= "    dbo:Country <$countryUri> ;\n";

        }
    }
    
    if (isset($values['dcterms:date'])) {
        foreach ($values['dcterms:date']['values'] as $value) {
            $ttl .= "    dct:date \"" . $value->value() . "\"^^xsd:literal ;\n";
        }
    }
    
    if (isset($values['dcterms:hasFormat'])) {
        foreach ($values['dcterms:hasFormat']['values'] as $value) {
            if ($value->uri()) {
                $ttl .= "    edm:Webresource <" . $value->uri() . "> ;\n";
            }
        }
    }

    if (!empty($entitiesToDeclare)) {
        $ttl .= "\n# Type declarations for referenced resources\n";
        
        if (isset($entitiesToDeclare['district'])) {
            $ttl .= "<{$entitiesToDeclare['district']['uri']}> a dbo:District .\n";
        }
        
        if (isset($entitiesToDeclare['parish'])) {
            $ttl .= "<{$entitiesToDeclare['parish']['uri']}> a dbo:Parish .\n";
        }
        

        
        $ttl .= "\n";
    }
    
    return $ttl;
}

/**
 * This method processes the measurements with original URIs.
 * @param mixed $values
 * @param mixed $arrowheadUri
 * @param mixed $identifier
 * @return string
 */
private function processMeasurementsWithOriginalUris($values, $arrowheadUri, $identifier)
{
    $ttl = "";
    $hasAnyMeasurements = false;
    
    $measurements = [
        'Height' => ['height', 'schema:height', null],
        'Width' => ['width', 'schema:width', null],
        'Weight' => ['weight', 'schema:weight', null], 
        'Thickness' => ['depth', 'schema:depth', null],
        'Body Length' => ['bodylength', 'ah:hasBodyLength', null],
        'Base Length' => ['baselength', 'ah:hasBaseLength', null],
    ];
    
    $measurementObjects = "";
    
    foreach ($measurements as $label => $config) {
        $suffix = $config[0];
        $property = $config[1];
        $defaultUnit = $config[2];
        
        if (isset($values[$label]) && !empty($values[$label]['values'])) {
            foreach ($values[$label]['values'] as $value) {
                $measurementValue = $value->value();
                $hasAnyMeasurements = true;
                
                if (preg_match('/^([0-9.]+)\s*([A-Z]+)?/', $measurementValue, $matches)) {
                    $numericValue = $matches[1];
                    $unit = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $defaultUnit;
                    
                    if ($property === 'schema:weight') {
                        $measurementUri = "$arrowheadUri/weight/$identifier-weight";
                        $measurementObjects .= "<$measurementUri> a excav:Weight ;\n";
                        $measurementObjects .= "    schema:value \"$numericValue\"^^xsd:decimal ;\n";
                        $measurementObjects .= "    schema:UnitCode <http://qudt.org/vocab/unit/$unit> .\n\n";
                    } elseif (strpos($property, 'ah:') === 0) {
                        $propName = str_replace('ah:has', '', $property);
                        $measurementUri = "$arrowheadUri/$suffix/$identifier-$suffix";
                        $measurementObjects .= "<$measurementUri> a excav:TypometryValue ;\n";
                        $measurementObjects .= "    schema:value \"$numericValue\"^^xsd:decimal ;\n";
                        $measurementObjects .= "    schema:UnitCode <http://qudt.org/vocab/unit/$unit> .\n\n";
                    } else {
                        $propName = str_replace('schema:', '', $property);
                        $measurementUri = "$arrowheadUri/typometry/$identifier-$propName";
                        $measurementObjects .= "<$measurementUri> a excav:TypometryValue ;\n";
                        $measurementObjects .= "    schema:value \"$numericValue\"^^xsd:decimal ;\n";
                        $measurementObjects .= "    schema:UnitCode <http://qudt.org/vocab/unit/$unit> .\n\n";
                    }
                }
            }
        }
    }
    
    if ($hasAnyMeasurements && $measurementObjects) {
        $ttl .= "# =========== TYPOMETRY VALUES ===========\n\n";
        $ttl .= $measurementObjects;
    }
    
    return $ttl;
}



/**
 * This method processes the morphology data and generates the TTL string.
 * It uses original URIs for morphology properties.
 * @param mixed $values
 * @param mixed $arrowheadUri
 * @param mixed $identifier
 * @return string
 */
private function processMorphologyWithOriginalUris($values, $arrowheadUri, $identifier)
{
    $ttl = "";
    $morphologyUri = "$arrowheadUri/morphology/$identifier-morphology";
    
    $morphologyProperties = ['ah:point', 'ah:body', 'ah:base', 'Point Definition (Sharp/Fractured)', 
                          'Body Symmetry (Symmetrical/Non-symmetrical)', 'Base Type'];
    
    $hasMorphologyData = false;
    foreach ($morphologyProperties as $prop) {
        if (isset($values[$prop])) {
            $hasMorphologyData = true;
            break;
        }
    }
    
    if ($hasMorphologyData) {
        $ttl .= "# =========== MORPHOLOGY ===========\n\n";
        $ttl .= "<$morphologyUri> a ah:Morphology ;\n";
        
        $morphologyStatements = [];
        
        if (isset($values['ah:point'])) {
            foreach ($values['ah:point']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'sharp') ? 'true' : 'false';
                $morphologyStatements[] = "    ah:point $boolValue";
            }
        } else if (isset($values['Point Definition (Sharp/Fractured)'])) {
            foreach ($values['Point Definition (Sharp/Fractured)']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'sharp') ? 'true' : 'false';
                $morphologyStatements[] = "    ah:point $boolValue";
            }
        }
        
        if (isset($values['ah:body'])) {
            foreach ($values['ah:body']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'symmetrical') ? 'true' : 'false';
                $morphologyStatements[] = "    ah:body $boolValue";
            }
        } else if (isset($values['Body Symmetry (Symmetrical/Non-symmetrical)'])) {
            foreach ($values['Body Symmetry (Symmetrical/Non-symmetrical)']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true' || strtolower($value->value()) === 'symmetrical') ? 'true' : 'false';
                $morphologyStatements[] = "    ah:body $boolValue";
            }
        }
        
        if (isset($values['ah:base'])) {
            foreach ($values['ah:base']['values'] as $value) {
                $baseValue = strtolower($value->value());
                $baseValue = preg_replace('/\s+/', '-', $baseValue); 
                $morphologyStatements[] = "    ah:base <https://purl.org/megalod/kos/ah-base/$baseValue>";
            }
        } else if (isset($values['Base Type'])) {
            foreach ($values['Base Type']['values'] as $value) {
                $baseValue = strtolower($value->value());
                $baseValue = preg_replace('/\s+/', '-', $baseValue); 
                $morphologyStatements[] = "    ah:base <https://purl.org/megalod/kos/ah-base/$baseValue>";
            }
        }
        
        if (!empty($morphologyStatements)) {
            $ttl .= implode(" ;\n", $morphologyStatements) . " .\n\n";
        } else {
            $ttl .= "    .\n\n"; 
        }
    }
    
    return $ttl;
}

/**
 * This method processes the chipping data and generates the TTL string.
 * It uses original URIs for chipping properties.
 * It checks for the presence of chipping data and constructs the appropriate URIs.
 * If chipping data is present, it generates a chipping object with the relevant properties.
 * @param mixed $values
 * @param mixed $arrowheadUri
 * @param mixed $identifier
 * @return string
 */
private function processChippingWithOriginalUris($values, $arrowheadUri, $identifier)
{
    $ttl = "";
    $chippingUri = "$arrowheadUri/chipping/$identifier-chipping";
    
    $chippingProperties = ['ah:chippingMode', 'ah:chippingAmplitude', 'ah:chippingDirection', 
                          'ah:chippingOrientation', 'ah:chippingDelineation', 'ah:chippingLocationSide',
                          'ah:chippingLocationTransversal', 'ah:chippingShape'];
    
    $hasChippingData = false;
    foreach ($chippingProperties as $prop) {
        if (isset($values[$prop])) {
            $hasChippingData = true;
            break;
        }
    }
    
    if ($hasChippingData) {
        
        $ttl .= "\n# =========== CHIPPING ===========\n\n";
        $ttl .= "<$chippingUri> a ah:Chipping ;\n";
        
        if (isset($values['ah:chippingMode'])) {
            foreach ($values['ah:chippingMode']['values'] as $value) {
                $modeValue = strtolower($value->value());
                $ttl .= "    ah:chippingMode <https://purl.org/megalod/kos/ah-chippingMode/$modeValue> ;\n";
            }
        }
        
        if (isset($values['ah:chippingAmplitude'])) {
            foreach ($values['ah:chippingAmplitude']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
                $ttl .= "    ah:chippingAmplitude $boolValue ;\n";
            }
        }
        
        if (isset($values['ah:chippingDirection'])) {
            foreach ($values['ah:chippingDirection']['values'] as $value) {
                $directionValue = strtolower($value->value());
                $ttl .= "    ah:chippingDirection <https://purl.org/megalod/kos/ah-chippingDirection/$directionValue> ;\n";
            }
        }
        
        if (isset($values['ah:chippingOrientation'])) {
            foreach ($values['ah:chippingOrientation']['values'] as $value) {
                $boolValue = (strtolower($value->value()) === 'true') ? 'true' : 'false';
                $ttl .= "    ah:chippingOrientation $boolValue ;\n";
            }
        }
        
        if (isset($values['ah:chippingDelineation'])) {
            foreach ($values['ah:chippingDelineation']['values'] as $value) {
                $delineationValue = strtolower($value->value());
                $ttl .= "    ah:chippingDelineation <https://purl.org/megalod/kos/ah-chippingDelineation/$delineationValue> ;\n";
            }
        }
        
        if (isset($values['ah:chippingLocationSide'])) {
            foreach ($values['ah:chippingLocationSide']['values'] as $value) {
                $locationValue = strtolower($value->value());
                $ttl .= "    ah:chippingLocationSide <https://purl.org/megalod/kos/ah-chippingLocation/$locationValue> ;\n";
            }
        }
        
        if (isset($values['ah:chippingLocationTransversal'])) {
            foreach ($values['ah:chippingLocationTransversal']['values'] as $value) {
                $locationValue = strtolower($value->value());
                $ttl .= "    ah:chippingLocationTransversal <https://purl.org/megalod/kos/ah-chippingLocation/$locationValue> ;\n";
            }
        }
        
        if (isset($values['ah:chippingShape'])) {
            foreach ($values['ah:chippingShape']['values'] as $value) {
                $shapeValue = strtolower($value->value());
                $ttl .= "    ah:chippingShape <https://purl.org/megalod/kos/ah-chippingShape/$shapeValue> ;\n";
            }
        }
        
        $ttl = rtrim($ttl, ";\n") . " .\n\n";
    }
    
    return $ttl;
}

/**
 * This method checks if the values contain coordinates data.
 * It looks for specific properties that indicate coordinates information.
 * @param mixed $values
 * @return bool True if coordinates data is present, false otherwise
 */
private function hasCoordinatesData($values)
{
    return isset($values['Coordinates']) || isset($values['excavation:hasCoordinatesInSquare']);
}


/**
 * This method processes the coordinates data and generates the TTL string.
 * It uses original URIs for coordinates properties.
 * It checks for the presence of coordinates data and constructs the appropriate URIs.
 * If coordinates data is present, it generates a coordinates object with the relevant properties.
 * @param mixed $values
 * @param mixed $arrowheadUri
 * @param mixed $identifier
 * @return string
 */
private function processCoordinatesWithOriginalUris($values, $arrowheadUri, $identifier)
{
    $ttl = "";
    $coordinateProperties = ['Coordinates', 'excavation:hasCoordinatesInSquare'];
    $axes = ['x', 'y', 'z'];
    $found = ['x' => null, 'y' => null, 'z' => null];

    foreach ($coordinateProperties as $property) {
        if (isset($values[$property])) {
            foreach ($values[$property]['values'] as $value) {
                $coordString = $value->value();
                if (preg_match_all('/([XYZ]):\s*([0-9.]+)/', $coordString, $matches, PREG_SET_ORDER)) {
                    $coordinatesUri = "$arrowheadUri/coordinates/$identifier-coordinates";
                    $ttl .= "\n# =========== COORDINATES IN SQUARE ===========\n\n";
                    $ttl .= "<$coordinatesUri> a excav:Coordinates ;\n";
                    foreach ($matches as $match) {
                        $axis = strtolower($match[1]);
                        $found[$axis] = $match[2];
                        $typometryUri = "$arrowheadUri/typometry/$identifier-$axis";
                        if ($axis === 'x') {
                            $ttl .= "    geo:long <$typometryUri> ;\n";
                        } elseif ($axis === 'y') {
                            $ttl .= "    geo:lat <$typometryUri> ;\n";
                        } else {
                            $ttl .= "    schema:depth <$typometryUri> ;\n";
                        }
                    }
                    $ttl = rtrim($ttl, ";\n") . " .\n\n";
                    // declare axis (X, Y, Z)
                    foreach ($axes as $axis) {
                        $typometryUri = "$arrowheadUri/typometry/$identifier-$axis";
                        $ttl .= "<$typometryUri> a excav:TypometryValue ;\n";
                        if ($found[$axis] !== null) {
                            $ttl .= "    schema:value \"{$found[$axis]}\"^^xsd:decimal ;\n";
                        } else {
                            $ttl .= "    schema:value \"\"^^xsd:decimal ;\n";
                        }
                        $ttl .= "    schema:UnitCode <http://qudt.org/vocab/unit/CMT> .\n\n";
                    }
                    break 2;
                }
            }
        }
    }
    return $ttl;
}

/**
 * This method processes the GPS coordinates data and generates the TTL string.
 * It uses original URIs for GPS properties.
 * It checks for the presence of GPS coordinates data and constructs the appropriate URIs.
 * If GPS coordinates data is present, it generates a GPS object with the relevant properties.
 * @param mixed $values
 * @param mixed $originalBaseUri
 * @param mixed $identifier
 * @return string
 */
private function processGPSWithOriginalUris($values, $originalBaseUri, $identifier)
{
    $ttl = "";
    
    if (isset($values['excavation:hasGPSCoordinates'])) {
        foreach ($values['excavation:hasGPSCoordinates']['values'] as $value) {
            $gpsString = $value->value();
            
            // Parse GPS string "lat: _, Long:_"
            if (preg_match('/Lat:\s*([0-9.-]+),\s*Long:\s*([0-9.-]+)/', $gpsString, $matches)) {
                $lat = $matches[1];
                $long = $matches[2];
                
                $excavationId = $this->extractExcavationId($values);
                $gpsUri = "$originalBaseUri/excavation/$excavationId/gps/$identifier-gps";
                                
                $ttl .= "\n# =========== GPS COORDINATES ===========\n\n";
                $ttl .= "<$gpsUri> a excav:GPSCoordinates ;\n";
                $ttl .= "    geo:lat \"$lat\"^^xsd:decimal ;\n";
                $ttl .= "    geo:long \"$long\"^^xsd:decimal .\n\n";
            }
        }
    }
    
    return $ttl;
}

/**
 * This method extracts the excavation ID from the values.
 * It looks for the excavation context reference and extracts the ID from the URI.
 * @param mixed $values The values from the resource
 * @return string The excavation ID or 'unknown' if not found
 */
private function extractExcavationId($values)
{
    $contextProperties = [
        'excavation:foundInLocation',
        'excavation:foundInSquare', 
        'excavation:foundInContext',
        'excavation:foundInSVU'
    ];
    
    foreach ($contextProperties as $property) {
        if (isset($values[$property])) {
            foreach ($values[$property]['values'] as $value) {
                if ($value->uri()) {
                    $uri = $value->uri();
                    if (preg_match('/\/excavation\/([^\/]+)\//', $uri, $matches)) {
                        return $matches[1];
                    }
                }
            }
        }
    }
    
    return 'unknown';
}






/**
 * This method escapes special characters in a string for use in TTL format.
 * It replaces quotes, backslashes, and control characters with their escaped versions.
 * @param string $string The string to escape
 * @return string The escaped string
 */
private function escapeTtlString($string)
{
    return str_replace(
        ['"', '\\', "\n", "\r", "\t"],
        ['\"', '\\\\', '\\n', '\\r', '\\t'],
        $string
    );
}




}
