<?php

declare(strict_types=1);

namespace App\Controller\Api\Web;

use App\Dto\Request\Pageable;
use App\Dto\Response\PageableResult;
use App\Entity\Company;
use App\Entity\User;
use App\Model\CompanyInterface;
use App\Repository\Interfaces\UserRepositoryInterface;
use App\Service\UserManager\Request\AttributeRequest;
use App\Service\UserManager\Request\ManyAttributesRequest;
use App\Service\UserManager\UserManagerInterface;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as Sensio;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Class UserController
 */
class UserController
{
    /**
     * @Rest\Get("/users", name="userList")
     * @Rest\View(serializerGroups={"Default"})
     *
     * @param Request $request
     * @param CompanyInterface $company
     * @param UserRepositoryInterface $userRepository
     * @return PageableResult
     */
    public function list(
        Request $request,
        CompanyInterface $company,
        UserRepositoryInterface $userRepository
    ): PageableResult {
        //manually modify this config to sort on email, field names are different for the user
        $sort = (string)str_replace('email.', 'emailCanonical.', $request->query->get('sort'));

        return $userRepository->paginateUsers(
            Pageable::of(
                $request->query->getInt('page', 1),
                $request->query->getInt('limit', 25),
                $sort
            ),
            $company,
            false
        );
    }

    /**
     * @Rest\Get("/users/roles", name="usersValidRoles")
     * @Rest\View(serializerGroups={"Default"})
     *
     * @param Security $security
     * @param RoleHierarchyInterface $roleHierarchy
     *
     * @return array
     */
    public function getValidRoles(Security $security, RoleHierarchyInterface $roleHierarchy): array
    {
        $roles = $security->getUser()->getRoles();

        return array_values(array_unique($roleHierarchy->getReachableRoleNames($roles)));
    }

    /**
     * @Rest\Get("/users/{id}", name="userDetail")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @Sensio\Security("company == user.getCompany()", message="You are not authorized to view this.")
     *
     * @Sensio\ParamConverter(name="user", options={"entity_manager": "slave"})
     *
     * @param User $user
     * @param CompanyInterface $company
     * @return User
     */
    public function detail(User $user, CompanyInterface $company): User
    {
        return $user;
    }

    /**
     * @Rest\Patch("/users", name="usersEdit")
     * @Rest\View(serializerGroups={"Default", "detail"}, statusCode=201)
     *
     * @Sensio\Security("company == user.getCompany()", message="Permission denied")
     *
     * @Sensio\ParamConverter(name="user", converter="fos_rest.request_body")
     *
     * @param User $user
     * @param CompanyInterface $company
     * @param ConstraintViolationListInterface $constraintViolationList
     * @param UserManagerInterface $userManager
     *
     * @return View
     */
    public function edit(
        User $user,
        CompanyInterface $company,
        ConstraintViolationListInterface $constraintViolationList,
        UserManagerInterface $userManager
    ): View {
        if (count($constraintViolationList) > 0) {
            return View::create($constraintViolationList, Response::HTTP_BAD_REQUEST);
        }

        $userManager->update($user);

        return View::create($user, Response::HTTP_OK);
    }

    /**
     * @Rest\Post("/users", name="usersCreate")
     * @Rest\View(serializerGroups={"Default", "detail"}, statusCode=201)
     *
     * @Sensio\ParamConverter(name="user", converter="fos_rest.request_body")
     * @Sensio\Security("is_granted('ROLE_OWNER') or is_granted('ROLE_USER')")
     *
     * @param User $user
     * @param CompanyInterface $company
     * @param ConstraintViolationListInterface $constraintViolationList
     * @param UserManagerInterface $userManager
     * @return View
     */
    public function create(
        User $user,
        CompanyInterface $company,
        ConstraintViolationListInterface $constraintViolationList,
        UserManagerInterface $userManager
    ): View {

        if ($company instanceof Company) {
            $user->setCompany($company);
        }

        if (count($constraintViolationList)) {
            return View::create($constraintViolationList, Response::HTTP_BAD_REQUEST);
        }

        $userManager->create($user);

        return View::create($user, Response::HTTP_CREATED);
    }

    /**
     * @Rest\Patch("/users/{id}/resetpassword", name="usersResetPassword")
     * @Rest\View(statusCode=201)
     *
     * @param User $user
     * @param Request $request
     * @param UserManagerInterface $userManager
     * @param Security $security
     */
    public function resetPassword(
        User $user,
        Request $request,
        UserManagerInterface $userManager,
        Security $security
    ): void {
        if ($request->get('password') === null) {
            throw new BadRequestHttpException('You must provide a new password.');
        }
        if (
            $security->getUser()
            && $security->getUser() !== $user
            && !in_array('ROLE_SUPER_ADMIN', $security->getUser()->getRoles(), true)
        ) {
            throw new UnauthorizedHttpException('', 'Setting password for this user is not allowed.');
        }
        $userManager->setPassword($user, $request->get('password'));
    }

    /**
     * @Rest\Delete("/users/{id}", name="usersDelete")
     * @Rest\View(statusCode=204)
     * @param User $user
     * @param UserManagerInterface $manager
     */
    public function delete(User $user, UserManagerInterface $manager): void
    {
        $manager->delete($user);
    }

    /**
     * @Rest\PATCH("/user/attribute")
     * @Rest\View(statusCode=201)
     *
     * @ParamConverter(name="request", converter="fos_rest.request_body")
     *
     * @param Security $security
     * @param AttributeRequest $request
     * @param UserManagerInterface $userManager
     * @return UserInterface
     */
    public function setPreference(
        Security $security,
        AttributeRequest $request,
        UserManagerInterface $userManager
    ): UserInterface {
        $user = $security->getUser();
        $userManager->saveUserAttribute($user, $request);

        return $user;
    }

    /**
     * @Rest\PATCH("/user/attributes")
     * @Rest\View(statusCode=201)
     *
     * @ParamConverter(name="request", converter="fos_rest.request_body")
     *
     * @param Security $security
     * @param ManyAttributesRequest $request
     * @param UserManagerInterface $userManager
     * @return UserInterface
     */
    public function setManyPreferences(
        Security $security,
        ManyAttributesRequest $request,
        UserManagerInterface $userManager
    ): UserInterface {
        $user = $security->getUser();
        $userManager->saveUserAttributes($user, $request);

        return $user;
    }

    /**
     * @Rest\Get("/user")
     * @Rest\View(statusCode=200)
     *
     * @param Security $security
     * @param UserRepositoryInterface $userRepository
     * @return User
     */
    public function getCurrentUser(
        Security $security,
        UserRepositoryInterface $userRepository
    ): User {
        $user = $security->getUser();
        if ($user instanceof User) {
            try {
                return $userRepository->get($user->getId());
            } catch (Exception $e) {
                throw new NotFoundHttpException('User not found', $e);
            }
        }

        throw new NotFoundHttpException('User not found');
    }
}
