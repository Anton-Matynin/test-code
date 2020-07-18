<?php

declare(strict_types=1);

namespace App\Controller\Api\Web;

use App\Dto\Request\Pageable;
use App\Dto\Response\PageableResult;
use App\Model\CompanyInterface;
use App\Repository\OperationRepository;
use FOS\RestBundle\Controller\Annotations as Rest;

/**
 * Class OperationController
 *
 * @Rest\Route("/operations")
 *
 * @package App\Controller\Api\Web
 */
class OperationController
{
    /**
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @param string $id
     * @param CompanyInterface $company
     * @param Pageable $pageable
     * @param OperationRepository $operationRepository
     * @return PageableResult
     */
    public function list(
        string $id,
        CompanyInterface $company,
        Pageable $pageable,
        OperationRepository $operationRepository
    ): PageableResult {
        return $operationRepository->paginate(
            $pageable,
            [
                $operationRepository->whereEntityId($id),
            ],
            true,
            false
        );
    }
}
