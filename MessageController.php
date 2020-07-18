<?php


namespace App\Controller\Api\Customer;

use App\Dto\Request\Pageable;
use App\Entity\Message;
use App\Model\CompanyInterface;
use App\Service\CustomerApi\MessageManager\MessageService;
use App\Service\CustomerApi\MessageManager\Request\CreateMessageRequest;
use App\Service\CustomerApi\MessageManager\UserMessageServiceInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as Sensio;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;


/**
 * Class MessageController
 *
 * @Rest\Route("/messages")
 */
class MessageController
{
    /**
     * @Rest\Get("/dispatch")
     * @Rest\View()
     *
     * @Sensio\Security("company.isDevicesEnabled() === true", message="Forbidden action. Company devices feature is not enabled")
     * @Sensio\Security("is_granted('ROLE_MESSAGE_READ')")
     *
     * @QueryParam(name="acknowledged", default="false")
     *
     * @param Pageable $pageable
     * @param ParamFetcherInterface $fetcher
     * @param UserMessageServiceInterface $service
     * @param CompanyInterface $company
     * @return View
     */
    public function inboxList(
        Pageable $pageable,
        ParamFetcherInterface $fetcher,
        UserMessageServiceInterface $service,
        CompanyInterface $company
    ): View {
        $pageable = $service->inboxMessages($pageable, $company, (bool)$fetcher->get('acknowledged'));

        return View::create($pageable, Response::HTTP_OK);
    }

    /**
     * @Rest\Post("/dispatch")
     * @Rest\View()
     *
     * @Sensio\ParamConverter("message", converter="fos_rest.request_body")
     *
     * @Sensio\Security("company.isDevicesEnabled() === true", message="Forbidden action. Company devices feature is not enabled")
     * @Sensio\Security("is_granted('ROLE_MESSAGE_WRITE')")
     *
     * @param CreateMessageRequest $message
     * @param ConstraintViolationListInterface $constraintViolationList
     * @param UserMessageServiceInterface $service
     * @param CompanyInterface $company
     *
     * @return View
     */
    public function postMessage(
        CreateMessageRequest $message,
        ConstraintViolationListInterface $constraintViolationList,
        UserMessageServiceInterface $service,
        CompanyInterface $company
    ): View {
        if (count($constraintViolationList)) {
            return View::create($constraintViolationList, Response::HTTP_BAD_REQUEST);
        }

        $result = $service->createMessage($message, $company);

        return View::create($result, Response::HTTP_CREATED);
    }

    /**
     * @Rest\Post("/{messageId}/acknowledge")
     * @Rest\View(statusCode=204)
     *
     * @Sensio\ParamConverter(name="message", options={"id":"messageId"})
     * @Sensio\Security("is_granted('ROLE_MESSAGE_READ')")
     *
     * @Sensio\Security("message.getCompany() == company", message="You do not have permission to modify this.")
     *
     * @param Message $message
     * @param MessageService $service
     * @param CompanyInterface $company
     */
    public function acknowledgeMessage(Message $message, MessageService $service, CompanyInterface $company): void
    {
        $service->acknowledgeMessage($message);
    }
}
