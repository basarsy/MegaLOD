<?php

namespace AddTriplestore\Controller\Site;

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
use AddTriplestore\Service\Site\ArrowheadItemClassifier;
use AddTriplestore\Service\Site\ResourceDetailPresentationService;
use AddTriplestore\Service\Site\SiteResourceSearchService;
use AddTriplestore\Service\Site\SiteSearchQuery;
use AddTriplestore\Service\Site\UploadRequestContext;
use AddTriplestore\Service\Site\UserContributedResourcesService;
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
use AddTriplestore\Service\Ttl\XmlToTtlPipeline;
use AddTriplestore\Service\Upload\TtlUploadOrchestrationService;
use Laminas\Http\Request as HttpRequest;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Laminas\Http\Client;
use EasyRdf\Graph;
use Laminas\Form\FormInterface;
use Laminas\Router\RouteStackInterface;
use Laminas\Session\Container;
use Laminas\Validator\Csrf as CsrfValidator;

/**
 * Omeka wires controller plugins at runtime; declare them for static analysis.
 *
 * @method \Omeka\Api\Representation\SiteRepresentation currentSite()
 * @method \Omeka\Entity\User|null identity()
 * @method \Omeka\Mvc\Controller\Plugin\Messenger messenger()
 * @method \Omeka\Api\Manager api()
 */
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

    /** @var ArrowheadItemClassifier */
    private $arrowheadItemClassifier;

    /** @var UserContributedResourcesService */
    private $userContributedResourcesService;

    /** @var ResourceDetailPresentationService */
    private $resourceDetailPresentationService;

    /** @var SiteResourceSearchService */
    private $siteResourceSearchService;

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
        TtlPresentationService $ttlPresentationService,
        ArrowheadCanonicalTtlExporter $arrowheadCanonicalTtlExporter,
        CollectingFormToExcavationDataMapper $collectingFormToExcavationDataMapper,
        OmekaSiteResourceService $omekaSiteResourceService,
        SiteUserAuthSupport $siteUserAuthSupport,
        TtlUploadOrchestrationService $ttlUploadOrchestrationService,
        ArrowheadItemClassifier $arrowheadItemClassifier,
        UserContributedResourcesService $userContributedResourcesService,
        ResourceDetailPresentationService $resourceDetailPresentationService,
        SiteResourceSearchService $siteResourceSearchService
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
        $this->ttlPresentationService = $ttlPresentationService;
        $this->arrowheadCanonicalTtlExporter = $arrowheadCanonicalTtlExporter;
        $this->collectingFormToExcavationDataMapper = $collectingFormToExcavationDataMapper;
        $this->omekaSiteResourceService = $omekaSiteResourceService;
        $this->siteUserAuthSupport = $siteUserAuthSupport;
        $this->ttlUploadOrchestrationService = $ttlUploadOrchestrationService;
        $this->arrowheadItemClassifier = $arrowheadItemClassifier;
        $this->userContributedResourcesService = $userContributedResourcesService;
        $this->resourceDetailPresentationService = $resourceDetailPresentationService;
        $this->siteResourceSearchService = $siteResourceSearchService;

        $this->graphdbEndpoint = $megalodConfig->getGraphdbRdfGraphsServiceUrl();
        $this->graphdbQueryEndpoint = $megalodConfig->getGraphdbQueryEndpoint();
        $this->baseDataGraphUri = $megalodConfig->getMegalodPublicBaseUri();
        $this->localBaseUri = $megalodConfig->getMegalodLocalBaseUri();
        $this->graphdbWorkbenchUrl = $megalodConfig->getGraphdbWorkbenchUrl();
    }

    private function httpRequest(): HttpRequest
    {
        $request = parent::getRequest();
        if (!$request instanceof HttpRequest) {
            throw new \RuntimeException('Expected HTTP request');
        }
        return $request;
    }

    private function httpResponse(): HttpResponse
    {
        $response = parent::getResponse();
        if (!$response instanceof HttpResponse) {
            throw new \RuntimeException('Expected HTTP response');
        }
        return $response;
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
        if (!$this->httpRequest()->isPost()) {
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

        if ($this->httpRequest()->isPost()) {
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
            return $this->redirect()->toRoute('site/add-triplestore', [
                'site-slug' => $this->currentSite()->slug()
            ]);
        }

        $form = $this->getServiceLocator()->get('FormElementManager')->get(\Omeka\Form\LoginForm::class);
        $view = new ViewModel([
            'form' => $form,
            'site' => $this->currentSite()
        ]);
        $view->setTemplate('add-triplestore/site/index/login');
        
        if ($this->httpRequest()->isPost()) {
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
                    return $this->redirect()->toRoute('site/add-triplestore', [
                        'site-slug' => $this->currentSite()->slug()
                    ]);
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
     * @return \Laminas\View\Model\ViewModel|\Laminas\Http\Response
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
            $lists = $this->userContributedResourcesService->getMyArrowheadListsForOwner((int) $userId);
            $items = $lists['items'];
            $itemSets = $lists['item_sets'];
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
        $request = $this->httpRequest();
        $requestedResourceType = $request->getQuery('type', 'item');
        $requestedId = (int) $request->getQuery('id');

        if (!$requestedId) {
            return $this->redirect()->toRoute('site/add-triplestore/search', ['site-slug' => $this->currentSite()->slug()]);
        }

        try {
            $detail = $this->resourceDetailPresentationService->buildPresentation($requestedResourceType, $requestedId);
        } catch (\Exception $e) {
            $this->messenger()->addError('The requested resource could not be found: ' . $e->getMessage());
            return $this->redirect()->toRoute('site/add-triplestore/search', ['site-slug' => $this->currentSite()->slug()]);
        }

        return new ViewModel([
            'resource' => $detail->resource,
            'resourceType' => $detail->requestedResourceType,
            'itemSetIdForLink' => $detail->itemSetIdForLink,
            'properties' => $detail->properties,
            'relatedItems' => $detail->relatedItems,
            'media' => $detail->media,
            'site' => $this->currentSite(),
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

        if ($this->httpRequest()->isPost() && !$this->validateCsrfToken()) {
            return $this->redirect()->toRoute('site/add-triplestore/upload', [
                'site-slug' => $this->currentSite()->slug(),
            ]);
        }

        $itemSetId = $this->params()->fromQuery('item_set_id');
        error_log("Processing collecting form for item set ID: $itemSetId", 3, OMEKA_PATH . '/logs/count-add-triplestore.log');
        $uploadType = $this->params()->fromQuery('upload_type', 'arrowhead');
        
        // Get data from the collecting form
        $formData = $this->params()->fromPost();
                
        $uploadedFiles = $this->extractCollectingFormUploadedFiles($_FILES);
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

        if ($this->httpRequest()->isPost() && !$this->validateCsrfToken()) {
            return $this->redirect()->toRoute('site/add-triplestore/upload', [
                'site-slug' => $this->currentSite()->slug(),
            ]);
        }

        $postData = $this->params()->fromPost();

        $uploadCtx = UploadRequestContext::fromRequestParameters(
            $this->httpRequest()->getQuery(),
            $this->httpRequest()->getPost()
        );
        $uploadType = $uploadCtx->uploadType;
        $itemSetId = $uploadCtx->itemSetId;
        $itemSetIdInt = $uploadCtx->getItemSetIdAsInt();
        $mode = $uploadCtx->mode;

        
        // Process arrowhead file upload
        if ($mode == 'file' && $uploadType == 'arrowhead' && $itemSetId) {
            $file = $this->params()->fromFiles('file');
            if ($file && !empty($file['tmp_name'])) {

                $result = $this->processFileUpload($this->httpRequest(), $uploadType, $itemSetIdInt);

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
            
            $result = $this->processFileUpload($this->httpRequest(), $uploadType, $itemSetIdInt);
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

        $response = $this->httpResponse();
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
                if ($this->arrowheadItemClassifier->isLikelyArrowheadResource($resource)) {
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
            
            $response = $this->httpResponse();
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
        $searchQueryDto = SiteSearchQuery::fromParameters($this->httpRequest()->getQuery());

        $archaeologistOptions = $this->siteMetadataOptionsService->getArchaeologistOptions();
        $countryOptions = $this->siteMetadataOptionsService->getCountryOptions();
        $districtOptions = $this->siteMetadataOptionsService->getDistrictOptions();
        $parishOptions = $this->siteMetadataOptionsService->getParishOptions();

        $searchResult = $this->siteResourceSearchService->search($searchQueryDto);

        return new ViewModel([
            'site' => $this->currentSite(),
            'searchQuery' => $searchQueryDto->searchQuery,
            'searchType' => $searchQueryDto->searchType,
            'results' => $searchResult->results,
            'totalResults' => $searchResult->getTotalResults(),
            'totalItems' => $searchResult->totalItems,
            'totalItemSets' => $searchResult->totalItemSets,
            'archaeologistOptions' => $archaeologistOptions,
            'countryOptions' => $countryOptions,
            'districtOptions' => $districtOptions,
            'parishOptions' => $parishOptions,
            'filterArchaeologist' => $searchQueryDto->filterArchaeologist,
            'filterOrcid' => $searchQueryDto->filterOrcid,
            'filterCountry' => $searchQueryDto->filterCountry,
            'filterDistrict' => $searchQueryDto->filterDistrict,
            'filterParish' => $searchQueryDto->filterParish,
        ]);
    }

    // ================== PREvent METHODS ==================

    /**
     * Pre-dispatch access control.
     * Calls parent preDispatch if available and prevents site-only users from accessing admin areas.
     */
    public function preDispatch(\Laminas\Mvc\MvcEvent $e)
    {
        // Call parent preDispatch when the Laminas/Omeka base provides it.
        if (method_exists(get_parent_class($this), 'preDispatch')) {
            // @phpstan-ignore staticMethod.notFound (not all AbstractActionController generations declare preDispatch)
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
        if (!$request instanceof HttpRequest) {
            return;
        }
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
                $allowedSite = $session->offsetExists('allowedSite') ? $session->offsetGet('allowedSite') : null;
                $siteSlug = (!empty($allowedSite) && is_string($allowedSite)) ? $allowedSite : $this->currentSite()->slug();
                
                $response = $e->getResponse();
                if (!$response instanceof HttpResponse) {
                    return;
                }
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
 * Extract files attached via the Collecting module into the multi-file
 * shape expected by OmekaRestSubmissionService::attachUploadedMediaToItem.
 *
 * Collecting wraps file inputs as <input name="file[<promptId>][]">, so
 * the raw $_FILES['file'] payload is keyed by prompt id.
 *
 * @param array<string, mixed> $files
 * @return array<string, array<int, mixed>>|null
 */
private function extractCollectingFormUploadedFiles(array $files): ?array
{
    if (empty($files['file']) || !is_array($files['file'])) {
        return null;
    }

    $names = $files['file']['name'] ?? null;
    if (!is_array($names)) {
        return null;
    }

    foreach ($names as $promptId => $promptNames) {
        if (!is_array($promptNames) || empty($promptNames)) {
            continue;
        }

        $hasUpload = false;
        foreach ($promptNames as $name) {
            if (is_string($name) && $name !== '') {
                $hasUpload = true;
                break;
            }
        }

        if (!$hasUpload) {
            continue;
        }

        return [
            'name' => $files['file']['name'][$promptId] ?? [],
            'type' => $files['file']['type'][$promptId] ?? [],
            'tmp_name' => $files['file']['tmp_name'][$promptId] ?? [],
            'error' => $files['file']['error'][$promptId] ?? [],
            'size' => $files['file']['size'][$promptId] ?? [],
        ];
    }

    return null;
}




/**
 * This function processes the upload of a file
 * @param \Laminas\Http\Request $request
 * @param string|null $uploadType
 * @param int|null $itemSetId
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
                return 'Validation Error: ' . $e->getMessage();
            }
        }

        return $this->uploadTtlData($ttlData, $itemSetId);
    } catch (\Exception $e) {
        return 'Error processing file: ' . $e->getMessage();
    }
}

    

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
