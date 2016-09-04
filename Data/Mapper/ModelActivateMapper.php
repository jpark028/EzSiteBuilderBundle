<?php

namespace EdgarEz\SiteBuilderBundle\Data\Mapper;

use EdgarEz\SiteBuilderBundle\Data\Model\ModelActivateData;
use eZ\Publish\API\Repository\Values\ValueObject;
use EzSystems\RepositoryForms\Data\Mapper\FormDataMapperInterface;

class ModelActivateMapper implements FormDataMapperInterface
{
    /**
     * @param ValueObject $modelActivate
     * @param array       $params
     * @return ModelActivateData
     */
    public function mapToFormData(ValueObject $modelActivate, array $params = [])
    {
        $data = new ModelActivateData(['modelActivate' => $modelActivate]);

        return $data;
    }
}
