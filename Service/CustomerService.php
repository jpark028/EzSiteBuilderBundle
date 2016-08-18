<?php

namespace EdgarEz\SiteBuilderBundle\Service;

use EdgarEz\ToolsBundle\Service\Content;
use EdgarEz\ToolsBundle\Service\Role;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\RoleService;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\API\Repository\Values\User\Limitation\SubtreeLimitation;
use Symfony\Component\Yaml\Yaml;

/**
 * Class CustomerService
 * @package EdgarEz\SiteBuilderBundle\Service
 */
class CustomerService
{
    /** @var RoleService $roleService eZ Role Service */
    private $roleService;

    /** @var LocationService $locationService eZ Location Service */
    private $locationService;

    /** @var UserService $userService eZ User Service */
    private $userService;

    /** @var ContentTypeService $contentTypeService eZ ContentType Service */
    private $contentTypeService;

    /** @var Content $content EdgarEz Content Service */
    private $content;

    /** @var Role $role EdgarEz Role Service */
    private $role;

    /** @var array $siteaccessGroups ezpublish siteaccess groups */
    private $siteaccessGroups;

    /**
     * CustomerService constructor.
     *
     * @param RoleService $roleService eZ role Service
     * @param LocationService $locationService eZ Location Service
     * @param UserService $userService eZ User Service
     * @param ContentTypeService $contentTypeService eZ ContentType Service
     * @param Content $content EdgarEz Content Service
     * @param Role $role EdgarEz Role Service
     * @param array $siteaccessGroups ezpublish siteaccess groups
     */
    public function __construct(
        RoleService $roleService,
        LocationService $locationService,
        UserService $userService,
        ContentTypeService $contentTypeService,
        Content $content,
        Role $role,
        array $siteaccessGroups
    )
    {
        $this->roleService = $roleService;
        $this->locationService = $locationService;
        $this->userService = $userService;
        $this->contentTypeService = $contentTypeService;
        $this->content = $content;
        $this->role = $role;
        $this->siteaccessGroups = $siteaccessGroups;
    }

    /**
     * Create eZ Content
     *
     * @param int $parentLocationID parent location ID
     * @param string $name content name
     * @return \eZ\Publish\API\Repository\Values\Content\Content eZ Content
     */
    public function createContentStructure($parentLocationID, $name)
    {
        $contentDefinition = Yaml::parse(file_get_contents(__DIR__ . '/../Resources/datas/customercontent.yml'));
        $contentDefinition['parentLocationID'] = $parentLocationID;
        $contentDefinition['fields']['title']['value'] = $name;
        return $this->content->add($contentDefinition);
    }

    /**
     * Create media content structure
     *
     * @param int $parentLocationID parent location ID
     * @param string $name name
     * @return \eZ\Publish\API\Repository\Values\Content\Content eZ Content
     */
    public function createMediaContentStructure($parentLocationID, $name)
    {
        $contentDefinition = Yaml::parse(file_get_contents(__DIR__ . '/../Resources/datas/mediacustomercontent.yml'));
        $contentDefinition['parentLocationID'] = $parentLocationID;
        $contentDefinition['fields']['title']['value'] = $name;
        return $this->content->add($contentDefinition);
    }

    /**
     * Create user groups
     *
     * @param int $parentCreatorLocationID parent user creator groupe location ID
     * @param int $parentEditorLocationID parent user editor groupe location ID
     * @param string $name name
     * @return array
     */
    public function createUserGroups($parentCreatorLocationID, $parentEditorLocationID, $name)
    {
        $contents = array();

        $userGroupDefinition = Yaml::parse(file_get_contents(__DIR__. '/../Resources/datas/customerusergroup_creators.yml'));
        $userGroupDefinition['parentLocationID'] = $parentCreatorLocationID;
        $userGroupDefinition['fields']['name']['value'] = $name;
        $contents['customerUserCreatorsGroup'] = $this->content->add($userGroupDefinition);

        $userGroupDefinition = Yaml::parse(file_get_contents(__DIR__. '/../Resources/datas/customerusergroup_editors.yml'));
        $userGroupDefinition['parentLocationID'] = $parentEditorLocationID;
        $userGroupDefinition['fields']['name']['value'] = $name;
        $contents['customerUserEditorsGroup'] = $this->content->add($userGroupDefinition);

        return $contents;
    }

    /**
     * Create user roles
     *
     * @param string $customerName customer nme
     * @param int $customerLocationID customer content location ID
     * @param int $mediaCustomerLocationIcustomer media location ID
     * @param int $customerUserCreatorsGroupLocationID customer user creator group location ID
     * @param int $customerUserEditorsGroupLocationID customer user editor group location ID
     * @return array
     */
    public function createRoles(
        $customerName,
        $customerLocationID,
        $mediaCustomerLocationID,
        $customerUserCreatorsGroupLocationID,
        $customerUserEditorsGroupLocationID
    )
    {
        $returnValue = array();

        /** @var \eZ\Publish\API\Repository\Values\User\Role $roleCreator */
        $roleCreator = $this->role->add('SiteBuilder ' . $customerName . ' creator');
        $returnValue['customerRoleCreatorID'] = $roleCreator->id;
        $returnValue['roleCreator'] = $roleCreator;

        $this->role->addPolicy($roleCreator->id, 'content', 'read');
        $this->role->addPolicy($roleCreator->id, 'content', 'create');
        $this->role->addPolicy($roleCreator->id, 'content', 'edit');
        $this->role->addPolicy($roleCreator->id, 'user', 'login');

        $this->role->addPolicy($roleCreator->id, 'sitebuilder', 'sitecreate');
        $this->role->addPolicy($roleCreator->id, 'sitebuilder', 'siteactivate');

        /** @var \eZ\Publish\API\Repository\Values\User\Role $roleEditor */
        $roleEditor = $this->role->add('SiteBuilder ' . $customerName . ' editor');
        $returnValue['customerRoleEditorID'] = $roleEditor->id;
        $returnValue['roleEditor'] = $roleEditor;

        $this->role->addPolicy($roleEditor->id, 'content', 'read');
        $this->role->addPolicy($roleEditor->id, 'content', 'create');
        $this->role->addPolicy($roleEditor->id, 'content', 'edit');
        $this->role->addPolicy($roleEditor->id, 'user', 'login');

        // Manage policy subtree limitation to the roles
        $contentLocation = $this->locationService->loadLocation($customerLocationID);
        $mediaContentLocation = $this->locationService->loadLocation($mediaCustomerLocationID);

        $userGroupCreatorLocation = $this->locationService->loadLocation($customerUserCreatorsGroupLocationID);
        $userGroupCreator = $this->userService->loadUserGroup($userGroupCreatorLocation->contentId);
        $userGroupEditorLocation = $this->locationService->loadLocation($customerUserEditorsGroupLocationID);
        $userGroupEditor = $this->userService->loadUserGroup($userGroupEditorLocation->contentId);
        $subtreeLimitation = new SubtreeLimitation(
            array(
                'limitationValues' => array(
                    '/' . implode('/', $contentLocation->path) . '/',
                    '/' . implode('/', $mediaContentLocation->path) . '/'
                )
            )
        );

        $this->roleService->assignRoleToUserGroup(
            $roleCreator,
            $userGroupCreator,
            $subtreeLimitation
        );

        $this->roleService->assignRoleToUserGroup(
            $roleEditor,
            $userGroupEditor,
            $subtreeLimitation
        );

        $siteaccess = array();
        $siteaccessGroups = array_keys($this->siteaccessGroups);
        foreach ($siteaccessGroups as $sg) {
            if (strpos($sg, 'edgarezsb_models_') === 0) {
                $sg = substr($sg, strlen('edgarezsb_models_'));
                $siteaccess[] = sprintf('%u', crc32($sg));
            }
        }

        $this->role->addSiteaccessLimitation($roleCreator, $siteaccess);
        $this->role->addSiteaccessLimitation($roleEditor, $siteaccess);

        return $returnValue;
    }

    /**
     * Create user creator
     *
     * @param string $userFirstName first name
     * @param string $userLastName last name
     * @param string $userEmail email
     * @param int $customerUserCreatorsGroupLocationID group location ID
     * @return string
     */
    public function initializeUserCreator($userFirstName, $userLastName, $userEmail, $customerUserCreatorsGroupLocationID)
    {
        $userLogin = $userEmail;
        $userPassword = substr(str_shuffle(strtolower(sha1(rand() . time() . $userLogin))),0, 8);;

        $contentType = $this->contentTypeService->loadContentTypeByIdentifier('edgar_ez_sb_user');
        $userCreateStruct = $this->userService->newUserCreateStruct($userLogin, $userEmail, $userPassword, 'eng-GB', $contentType);
        $userCreateStruct->setField('first_name', $userFirstName);
        $userCreateStruct->setField('last_name', $userLastName);

        $userGroupCreatorLocation = $this->locationService->loadLocation($customerUserCreatorsGroupLocationID);
        $userGroup = $this->userService->loadUserGroup($userGroupCreatorLocation->contentId);

        $this->userService->createUser($userCreateStruct, array($userGroup));

        return $userPassword;
    }
}
