<?php

namespace Smile\EzSiteBuilderBundle\Command;

use Smile\EzSiteBuilderBundle\Generator\CustomerGenerator;
use Smile\EzSiteBuilderBundle\Generator\ProjectGenerator;
use Smile\EzSiteBuilderBundle\Mail\Sender;
use Smile\EzSiteBuilderBundle\Service\CustomerService;
use eZ\Publish\API\Repository\LanguageService;
use eZ\Publish\API\Repository\Repository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Class CustomerCommand
 * @package Smile\EzSiteBuilderBundle\Command
 */
class CustomerCommand extends BaseContainerAwareCommand
{
    /**
     * @var int $customerLocationID customer content location ID
     */
    protected $customerLocationID;

    /**
     * @var int $mediaCustomerLocationID media customer content location ID
     */
    protected $mediaCustomerLocationID;

    /**
     * @var int $customerUserCreatorsGroupLocationID customer user creators group location ID
     */
    protected $customerUserCreatorsGroupLocationID;

    /**
     * @var int $customerUserEditorsGroupLocationID customer user editors group location ID
     */
    protected $customerUserEditorsGroupLocationID;

    /** @var int $customerRoleCreatorID creator role ID */
    protected $customerRoleCreatorID;

    /** @var int $customerRoleEditorID editor role ID */
    protected $customerRoleEditorID;

    /** @var string $customerName customer name */
    protected $customerName;

    /** @var string $userFirstName first customer creator first name */
    protected $userFirstName;

    /** @var string $userLastName first customer creator last name */
    protected $userLastName;

    /** @var string $userEmail first customer creator email */
    protected $userEmail;

    /**
     * Configure Customer generator command
     */
    protected function configure()
    {
        $this
            ->setName('smile:sitebuilder:customer:generate')
            ->setDescription('Generate SiteBuilder Customer (Content Structure and Bundle)');
    }

    /**
     * Execute command
     *
     * @param InputInterface $input input console
     * @param OutputInterface $output output console
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $adminID = $this->getContainer()->getParameter('smile_ez_tools.adminid');
        /** @var Repository $repository */
        $repository = $this->getContainer()->get('ezpublish.api.repository');
        $repository->setCurrentUser($repository->getUserService()->loadUser($adminID));

        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'SiteBuilder Customer initialization');

        $this->getVendorNameDir();

        $this->askContentStructure($input, $output);
        $this->askUserCreator($input, $output);

        /** @var CustomerService $customerService */
        $customerService = $this->getContainer()->get('smile_ez_site_builder.customer.service');
        /** @var LanguageService $languageService */
        $languageService = $repository->getContentLanguageService();

        try {
            $basename = ProjectGenerator::MAIN;

            $languageCode = $languageService->getDefaultLanguageCode();

            $parentLocationID = $this->getContainer()->getParameter(
                'smileez_sb.' . strtolower($basename) . '.default.customers_location_id'
            );
            $returnValue = $customerService->createContentStructure(
                $parentLocationID,
                $this->customerName,
                $languageCode
            );
            $this->customerLocationID = $returnValue['customerLocationID'];

            $parentLocationID = $this->getContainer()->getParameter(
                'smileez_sb.' . strtolower($basename) . '.default.media_customers_location_id'
            );
            $returnValue = $customerService->createMediaContentStructure(
                $parentLocationID,
                $this->customerName,
                $languageCode
            );
            $this->mediaCustomerLocationID = $returnValue['mediaCustomerLocationID'];

            $parentCreatorLocationID = $this->getContainer()->getParameter(
                'smileez_sb.' . strtolower($basename) . '.default.user_creators_location_id'
            );
            $parentEditorLocationID = $this->getContainer()->getParameter(
                'smileez_sb.' . strtolower($basename) . '.default.user_editors_location_id'
            );
            $returnValue = $customerService->createUserGroups(
                $parentCreatorLocationID,
                $parentEditorLocationID,
                $this->customerName,
                $languageCode
            );
            $this->customerUserCreatorsGroupLocationID = $returnValue['customerUserCreatorsGroupLocationID'];
            $this->customerUserEditorsGroupLocationID = $returnValue['customerUserEditorsGroupLocationID'];

            $returnValue = $customerService->createRoles(
                $this->customerName,
                $this->customerLocationID,
                $this->mediaCustomerLocationID,
                $this->customerUserCreatorsGroupLocationID,
                $this->customerUserEditorsGroupLocationID
            );
            $this->customerRoleCreatorID = $returnValue['customerRoleCreatorID'];
            $this->customerRoleEditorID = $returnValue['customerRoleEditorID'];

            $customerService->updateGlobalRole(
                $this->customerUserCreatorsGroupLocationID,
                $this->customerUserEditorsGroupLocationID
            );

            $this->initializeUserCreator($output, $languageCode);

            /** @var CustomerGenerator $generator */
            $generator = $this->getGenerator();
            $generator->generate(
                $this->customerLocationID,
                $this->mediaCustomerLocationID,
                $this->customerUserCreatorsGroupLocationID,
                $this->customerUserEditorsGroupLocationID,
                $this->customerRoleCreatorID,
                $this->customerRoleEditorID,
                $this->vendorName,
                $this->customerName,
                $this->dir
            );

            $namespace = $this->vendorName . '\\' . ProjectGenerator::CUSTOMERS . '\\' . $this->customerName .
                '\\' . CustomerGenerator::BUNDLE ;
            $bundle = $this->vendorName . ProjectGenerator::CUSTOMERS . $this->customerName .
                CustomerGenerator::BUNDLE;
            $this->updateKernel(
                $questionHelper,
                $input,
                $output,
                $this->getContainer()->get('kernel'),
                $namespace,
                $bundle
            );

            $output->writeln(array(
                '',
                $this->getHelper('formatter')->formatBlock(
                    'SiteBuilder Contents and Structure generated',
                    'bg=blue;fg=white',
                    true
                ),
                ''
            ));
        } catch (\RuntimeException $e) {
            $output->write('<error>' . $e->getMessage() . '</error');
        }
    }

    /**
     * Create customer root content
     *
     * @param InputInterface $input input console
     * @param OutputInterface $output output console
     */
    protected function askContentStructure(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();

        /** @var CustomerService $customerService */
        $customerService = $this->getContainer()->get('smile_ez_site_builder.customer.service');

        $customerName = false;
        $question = new Question($questionHelper->getQuestion('Customer name used to construct namespace', null));
        $question->setValidator(
            array(
                'Smile\EzSiteBuilderBundle\Command\Validators',
                'validateVendorName'
            )
        );

        while (!$customerName) {
            $customerName = $questionHelper->ask($input, $output, $question);
            $exists = $customerService->exists($customerName, $this->vendorName, $this->dir);
            if ($exists) {
                $output->writeln('<error>This customer already exists with this name</error>');
                $customerName = false;
            }
        }

        $this->customerName = $customerName;
    }

    /**
     * Initialize customer user creator
     *
     * @param InputInterface $input input console
     * @param OutputInterface $output output console
     */
    protected function askUserCreator(InputInterface $input, OutputInterface $output)
    {
        $questionHelper = $this->getQuestionHelper();
        $questionHelper->writeSection($output, 'Initialize customeruser creator account');

        $userFirstName = false;
        $question = new Question($questionHelper->getQuestion('First name', null));
        $question->setValidator(
            array(
                'Smile\EzSiteBuilderBundle\Command\Validators',
                'validateFirstName'
            )
        );

        while (!$userFirstName) {
            $userFirstName = $questionHelper->ask($input, $output, $question);
        }

        $this->userFirstName = $userFirstName;

        $userLastName = false;
        $question = new Question($questionHelper->getQuestion('Last name', null));
        $question->setValidator(
            array(
                'Smile\EzSiteBuilderBundle\Command\Validators',
                'validateLastName'
            )
        );

        while (!$userLastName) {
            $userLastName = $questionHelper->ask($input, $output, $question);
        }

        $this->userLastName = $userLastName;

        /** @var CustomerService $customerService */
        $customerService = $this->getContainer()->get('smile_ez_site_builder.customer.service');

        $userEmail = false;
        $question = new Question($questionHelper->getQuestion('User email', null));
        $question->setValidator(
            array(
                'Smile\EzSiteBuilderBundle\Command\Validators',
                'validateEmail'
            )
        );

        while (!$userEmail) {
            $userEmail = $questionHelper->ask($input, $output, $question);
            if ($customerService->emailExists($userEmail)) {
                $output->writeln('<error>User email already exist for another user</error>');
                $userEmail = false;
            }
        }

        $this->userEmail = $userEmail;
    }

    /**
     * Create first customer creator user
     *
     * @param OutputInterface $output output console
     */
    protected function initializeUserCreator(OutputInterface $output, $languageCode)
    {
        $questionHelper = $this->getQuestionHelper();

        /** @var CustomerService $customerService */
        $customerService = $this->getContainer()->get('smile_ez_site_builder.customer.service');
        $output->writeln('User creator initialized');

        $userPassword = $customerService->initializeUser(
            $languageCode,
            $this->userFirstName,
            $this->userLastName,
            $this->userEmail,
            $this->customerUserCreatorsGroupLocationID
        );

        /** @var Sender $mailer */
        $mailer = $this->getContainer()->get('smile_ez_site_builder.mailer');
        $mailer->send(
            'new user (' . $this->userEmail . '/' . $userPassword . ')',
            'new user',
            $this->getContainer()->getParameter('smile_ez_site_builder.sysadminemail'),
            $this->userEmail
        );

        $questionHelper->writeSection(
            $output,
            array(
                '',
                'user login: ' . $this->userEmail,
                'user password: ' . $userPassword,
                ''
            )
        );
    }

    /**
     * Initialize customer generator tool
     *
     * @return CustomerGenerator customer generator tool
     */
    protected function createGenerator()
    {
        return new CustomerGenerator(
            $this->getContainer()->get('filesystem'),
            $this->getContainer()->get('kernel')
        );
    }
}
