<?php
namespace Omeka\Controller;

use Omeka\Api\Exception as ApiException;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        return $this->redirect()->toRoute('site', ['site-slug' => 'megalod']);
    }
}
