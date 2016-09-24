<?php

namespace Smile\EzSiteBuilderBundle\Controller;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Smile\EzSiteBuilderBundle\Command\TaskCommand;
use Smile\EzSiteBuilderBundle\Entity\SiteBuilderTask;
use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\Core\MVC\Symfony\Security\User;
use EzSystems\PlatformUIBundle\Controller\Controller;
use EzSystems\RepositoryForms\Form\ActionDispatcher\ActionDispatcherInterface;
use Symfony\Component\Form\Form;

abstract class BaseController extends Controller
{
    protected function submitFuturTask($action, $minutes = 1)
    {
        $minutes = ($minutes > 1) ? '+' . $minutes . ' minutes' : '+' . $minutes . ' minutes';
        $task = new SiteBuilderTask();
        $postedAt = new \DateTime();
        $postedAt->modify($minutes);
        $this->submitTask($task, $action, $postedAt);
    }

    protected function submitTask(SiteBuilderTask $task, array $action, \DateTime $postedAt = null)
    {
        /** @var Registry $dcotrineRegistry */
        $doctrineRegistry = $this->get('doctrine');
        $doctrineManager = $doctrineRegistry->getManager();

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

    protected function getErrors(Form $form, $formID)
    {
        foreach ($form->getErrors(true) as $error) {
            $this->notifyErrorPlural(
                $error->getMessageTemplate(),
                $error->getMessagePluralization(),
                $error->getMessageParameters(),
                $formID
            );
        }
    }

    protected function dispatchFormAction(
        ActionDispatcherInterface $actionDispatcher,
        Form $form,
        ValueObject $data,
        array $options
    ) {
        $actionDispatcher->dispatchFormAction(
            $form,
            $data,
            $form->getClickedButton() ? $form->getClickedButton()->getName() : null,
            $options
        );
    }

    protected function initCacheTask($minutes = 1)
    {
        $action = array(
            'service'    => 'cache',
            'command'    => 'clear',
            'parameters' => array()
        );

        $this->submitFuturTask($action, $minutes);
    }
}
