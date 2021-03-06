<?php

namespace Smile\EzSiteBuilderBundle\Controller;

use Smile\EzSiteBuilderBundle\Data\Mapper\ModelActivateMapper;
use Smile\EzSiteBuilderBundle\Data\Mapper\ModelMapper;
use Smile\EzSiteBuilderBundle\Data\Model\ModelActivateData;
use Smile\EzSiteBuilderBundle\Data\Model\ModelData;
use Smile\EzSiteBuilderBundle\Entity\SiteBuilderTask;
use Smile\EzSiteBuilderBundle\Form\ActionDispatcher\ModelActivateDispatcher;
use Smile\EzSiteBuilderBundle\Form\ActionDispatcher\ModelDispatcher;
use Smile\EzSiteBuilderBundle\Form\Type\ModelActivateType;
use Smile\EzSiteBuilderBundle\Form\Type\ModelType;
use Smile\EzSiteBuilderBundle\Generator\ProjectGenerator;
use Smile\EzSiteBuilderBundle\Service\SecurityService;
use Smile\EzSiteBuilderBundle\Values\Content\Model;
use Smile\EzSiteBuilderBundle\Values\Content\ModelActivate;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Search\SearchResult;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class ModelController extends BaseController
{
    /** @var ModelDispatcher $actionDispatcher */
    protected $actionDispatcher;

    /** @var ModelActivateDispatcher $activateActionDispatcher */
    protected $activateActionDispatcher;

    /** @var ModelData $data */
    protected $data;

    /** @var ModelActivateData $dataActivate */
    protected $dataActivate;

    protected $tabItems;

    /** @var SecurityService $securityService */
    protected $securityService;

    /** @var SearchService $searchService */
    protected $searchService;

    public function __construct(
        ModelDispatcher $actionDispatcher,
        ModelActivateDispatcher $activateActionDispatcher,
        $tabItems,
        SecurityService $securityService,
        SearchService $searchService
    ) {
        $this->actionDispatcher = $actionDispatcher;
        $this->activateActionDispatcher = $activateActionDispatcher;
        $this->tabItems = $tabItems;
        $this->securityService = $securityService;
        $this->searchService = $searchService;
    }

    public function generateAction(Request $request)
    {
        $actionUrl = $this->generateUrl('smileezsb_sb', ['tabItem' => 'dashboard']);
        if (!$this->securityService->checkAuthorization('modelgenerate')) {
            return $this->redirectAfterFormPost($actionUrl);
        }

        $form = $this->getForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->dispatchFormAction($this->actionDispatcher, $form, $this->data, array(
                'modelName' => $this->data->modelName
            ));

            if ($response = $this->actionDispatcher->getResponse()) {
                return $response;
            }

            $this->initTask($form);
            $this->initAssetsTask($form);
            $this->initCacheTask(2);
            $this->initPolicyTask($form, 3);
            $this->initCacheTask(4);
            return $this->redirectAfterFormPost($actionUrl);
        }

        $this->getErrors($form, 'smileezsb_form_model');

        $tabItems = $this->tabItems;
        unset($tabItems[0]);
        return $this->render('SmileEzSiteBuilderBundle:sb:index.html.twig', [
            'tab_items' => $tabItems,
            'tab_item_selected' => 'modelgenerate',
            'params' => array('modelgenerate' => $form->createView()),
            'hasErrors' => true
        ]);
    }

    public function activateAction(Request $request)
    {
        $actionUrl = $this->generateUrl('smileezsb_sb', ['tabItem' => 'dashboard']);
        if (!$this->securityService->checkAuthorization('modelactivate')) {
            return $this->redirectAfterFormPost($actionUrl);
        }

        $form = $this->getActivateForm();
        $form->handleRequest($request);
        if ($form->isValid()) {
            $this->dispatchFormAction($this->activateActionDispatcher, $form, $this->dataActivate, array(
                'modelID' => $this->dataActivate->modelID
            ));

            if ($response = $this->activateActionDispatcher->getResponse()) {
                return $response;
            }

            $this->initActivateTask($form);
            $this->initCacheTask();
            return $this->redirectAfterFormPost($actionUrl);
        }

        $this->getErrors($form, 'smileezsb_form_modelactivate');

        $tabItems = $this->tabItems;
        unset($tabItems[0]);
        return $this->render('SmileEzSiteBuilderBundle:sb:index.html.twig', [
            'tab_items' => $tabItems,
            'tab_item_selected' => 'modelactivate',
            'params' => array(),
            'hasErrors' => true
        ]);
    }

    protected function getForm()
    {
        $model = new Model([
            'modelName' => 'Foo',
        ]);
        $this->data = (new ModelMapper())->mapToFormData($model);

        return $this->createForm(new ModelType(), $this->data);
    }

    protected function getActivateForm($modelID = null)
    {
        $modelActivate = new ModelActivate([
            'modelID' => $modelID,
        ]);
        $this->dataActivate = (new ModelActivateMapper())->mapToFormData($modelActivate);

        return $this->createForm(new ModelActivateType($modelID), $this->dataActivate);
    }

    protected function initTask(Form $form)
    {
        /** @var ModelData $data */
        $data = $form->getData();

        $action = array(
            'service'    => 'model',
            'command'    => 'generate',
            'parameters' => array(
                'modelName' => $data->modelName
            )
        );

        $task = new SiteBuilderTask();
        $this->submitTask($task, $action);
    }

    protected function initAssetsTask(Form $form, $minutes = 1)
    {
        /** @var ModelData $data */
        $data = $form->getData();

        $basename = ProjectGenerator::MAIN;
        $extensionAlias = 'smileez_sb.' . strtolower($basename);
        $vendorName = $this->container->getParameter($extensionAlias . '.default.vendor_name');

        $bundlePath = $vendorName . '\\' . ProjectGenerator::MODELS . '\\' .
            $data->modelName . 'Bundle';

        $bundleName = $vendorName . ProjectGenerator::MODELS .
            $data->modelName . 'Bundle';

        $action = array(
            'service'    => 'assets',
            'command'    => 'install',
            'parameters' => array(
                'bundlePath' => $bundlePath,
                'bundleName' => $bundleName
            )
        );

        $this->submitFuturTask($action, $minutes);
    }

    protected function initActivateTask(Form $form)
    {
        /** @var ModelActivateData $data */
        $data = $form->getData();

        $action = array(
            'service'    => 'model',
            'command'    => 'activate',
            'parameters' => array(
                'modelID' => $data->modelID
            )
        );

        $task = new SiteBuilderTask();
        $this->submitTask($task, $action);
    }

    protected function initPolicyTask(Form $form, $minutes = 1)
    {
        /** @var ModelData $data */
        $data = $form->getData();

        $action = array(
            'service'    => 'model',
            'command'    => 'policy',
            'parameters' => array(
                'modelName' => $data->modelName
            )
        );

        $this->submitFuturTask($action, $minutes);
    }

    public function listAction()
    {
        /** @var SearchResult $datas */
        $datas = $this->getModels();

        $models = array();
        if ($datas->totalCount) {
            foreach ($datas->searchHits as $data) {
                $models[] = array(
                    'data' => $data,
                    'form' => $this->getActivateForm($data->valueObject->contentInfo->mainLocationId)->createView()
                );
            }
        }

        return $this->render('SmileEzSiteBuilderBundle:sb:tab/model/list.html.twig', [
            'totalCount' => $datas->totalCount,
            'datas' => $models
        ]);
    }

    protected function getModels()
    {
        $query = new Query();
        $locationCriterion = new Query\Criterion\ParentLocationId(
            $this->container->getParameter('smileez_sb.project.default.models_location_id')
        );
        $contentTypeIdentifier = new Query\Criterion\ContentTypeIdentifier('smile_ez_sb_model');
        $activated = new Query\Criterion\Field('activated', Query\Criterion\Operator::EQ, false);

        $query->filter = new Query\Criterion\LogicalAnd(
            array($locationCriterion, $contentTypeIdentifier, $activated)
        );

        return $this->searchService->findContent($query);
    }
}
