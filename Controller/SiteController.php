<?php

namespace EdgarEz\SiteBuilderBundle\Controller;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use EdgarEz\SiteBuilderBundle\Command\TaskCommand;
use EdgarEz\SiteBuilderBundle\Data\Mapper\SiteMapper;
use EdgarEz\SiteBuilderBundle\Data\Site\SiteData;
use EdgarEz\SiteBuilderBundle\Entity\SiteBuilderTask;
use EdgarEz\SiteBuilderBundle\Form\ActionDispatcher\SiteDispatcher;
use EdgarEz\SiteBuilderBundle\Form\Type\SiteType;
use EdgarEz\SiteBuilderBundle\Generator\CustomerGenerator;
use EdgarEz\SiteBuilderBundle\Generator\ProjectGenerator;
use EdgarEz\SiteBuilderBundle\Service\SecurityService;
use EdgarEz\SiteBuilderBundle\Values\Content\Site;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\Core\MVC\Symfony\Security\User;
use EzSystems\PlatformUIBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class SiteController extends Controller
{
    /** @var LocationService $locationService */
    protected $locationService;

    /** @var SearchService $searchService */
    protected $searchService;

    /** @var SiteDispatcher $actionDispatcher */
    protected $actionDispatcher;

    /** @var SiteData $data */
    protected $data;

    protected $tabItems;

    /** @var SecurityService $securityService */
    protected $securityService;

    public function __construct(
        LocationService $locationService,
        SearchService $searchService,
        SiteDispatcher $actionDispatcher,
        $tabItems,
        SecurityService $securityService
    )
    {
        $this->locationService = $locationService;
        $this->searchService = $searchService;
        $this->actionDispatcher = $actionDispatcher;
        $this->tabItems = $tabItems;
        $this->securityService = $securityService;
    }

    public function generateAction(Request $request)
    {
        $actionUrl = $this->generateUrl('edgarezsb_sb', ['tabItem' => 'dashboard']);
        if (!$this->securityService->checkAuthorization('sitegenerate')) {
            return $this->redirectAfterFormPost($actionUrl);
        }

        $actionUrl = $this->generateUrl('edgarezsb_sb', ['tabItem' => 'dashboard']);
        $form = $this->getForm($request);
        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->actionDispatcher->dispatchFormAction(
                $form,
                $this->data,
                $form->getClickedButton() ? $form->getClickedButton()->getName() : null,
                array(
                    'siteName' => $this->data->siteName,
                    'host' => $this->data->host,
                    'mapuri' => $this->data->mapuri,
                    'model' => $this->data->customerContentLocationID . '-' . $this->data->customerMediaLocationID
                )
            );

            if ($response = $this->actionDispatcher->getResponse()) {
                return $response;
            }

            $this->initTask($form);
            $this->initPolicyTask($form);

            return $this->redirectAfterFormPost($actionUrl);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->notifyErrorPlural(
                $error->getMessageTemplate(),
                $error->getMessagePluralization(),
                $error->getMessageParameters(),
                'edgarezsb_form_site'
            );
        }

        $tabItems = $this->tabItems;
        unset($tabItems[0]);
        return $this->render('EdgarEzSiteBuilderBundle:sb:index.html.twig', [
            'tab_items' => $tabItems,
            'tab_item_selected' => 'sitegenerate',
            'params' => array('sitegenerate' => $form->createView()),
            'hasErrors' => true
        ]);
    }

    protected function getForm(Request $request)
    {
        $site = new Site([
            'siteName' => '',
            'model' => '',
            'host' => '',
            'mapuri' => false,
            'suffix' => '',
            'customerName' => '',
            'customerContentLocationID' => 0,
            'customerMediaLocationID' => 0,
        ]);
        $this->data = (new SiteMapper())->mapToFormData($site);

        $customerName = $this->getCustomerName();
        $customerAlias = ProjectGenerator::CUSTOMERS . $customerName . CustomerGenerator::SITES;

        $contentRootModelsLocationID = $this->container->getParameter('edgarez_sb.project.default.models_location_id');
        $mediaRootModelsLocationID = $this->container->getParameter('edgarez_sb.project.default.media_models_location_id');
        $contentRootCustomerLocationID = $this->container->getParameter('edgarez_sb.customer.' . Container::underscore($customerAlias) . '.default.customer_location_id');
        $mediaRootCustomerLocationID = $this->container->getParameter('edgarez_sb.customer.' . Container::underscore($customerAlias) . '.default.media_customer_location_id');
        return $this->createForm(
            new SiteType(
                $this->locationService,
                $this->searchService,
                $contentRootModelsLocationID,
                $mediaRootModelsLocationID,
                $contentRootCustomerLocationID,
                $mediaRootCustomerLocationID,
                $customerName
            ),
            $this->data
        );
    }

    protected function initTask(Form $form)
    {
        /** @var SiteData $data */
        $data = $form->getData();

        $action = array(
            'service'    => 'site',
            'command'    => 'generate',
            'parameters' => array(
                'siteName' => $data->siteName,
                'model' => $data->model,
                'host' => $data->host,
                'mapuri' => $data->mapuri,
                'suffix' => $data->suffix,
                'customerName' => $data->customerName,
                'customerContentLocationID' => $data->customerContentLocationID,
                'customerMediaLocationID' => $data->customerMediaLocationID,
            )
        );

        /** @var Registry $dcotrineRegistry */
        $doctrineRegistry = $this->get('doctrine');
        $doctrineManager = $doctrineRegistry->getManager();

        $task = new SiteBuilderTask();
        $this->submitTask($doctrineManager, $task, $action);
    }

    protected function initPolicyTask(Form $form)
    {
        /** @var SiteData $data */
        $data = $form->getData();

        $action = array(
            'service'    => 'site',
            'command'    => 'policy',
            'parameters' => array(
                'siteName' => $data->siteName,
                'model' => $data->model,
                'host' => $data->host,
                'mapuri' => $data->mapuri,
                'suffix' => $data->suffix,
                'customerName' => $data->customerName,
                'customerContentLocationID' => $data->customerContentLocationID,
                'customerMediaLocationID' => $data->customerMediaLocationID,
            )
        );

        /** @var Registry $dcotrineRegistry */
        $doctrineRegistry = $this->get('doctrine');
        $doctrineManager = $doctrineRegistry->getManager();

        $task = new SiteBuilderTask();
        $postedAt = new \DateTime();
        $postedAt->modify('+5 minutes');
        $this->submitTask($doctrineManager, $task, $action, $postedAt);
    }

    protected function submitTask(EntityManager $doctrineManager, SiteBuilderTask $task, array $action, \DateTime $postedAt = null)
    {
        $postedAt = $postedAt ? $postedAt : new \DateTime();
        try {
            $task->setAction($action);
            $task->setStatus(TaskCommand::STATUS_SUBMITTED);
            $task->setPostedAt($postedAt);
        } catch (\Exception $e) {
            $task->setLogs('Fail to generate task');
            $task->setStatus(TaskCommand::STATUS_FAIL);
        } finally {
            /** @var User $user */
            $user = $this->getUser();
            $task->setUserID($user->getAPIUser()->getUserId());

            $doctrineManager->persist($task);
            $doctrineManager->flush();
        }
    }

    protected function getCustomerName()
    {
        /** @var User $user */
        $user = $this->getUser();
        $userLocation = $this->locationService->loadLocation($user->getAPIUser()->contentInfo->mainLocationId);

        $parent = $this->locationService->loadLocation($userLocation->parentLocationId);
        return $parent->contentInfo->name;
    }
}
