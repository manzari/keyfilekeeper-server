<?php


namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Responses\EmptyResponse;
use App\Responses\ErrorResponse;
use App\Responses\JsonResponse;
use App\Responses\NoRightsResponse;
use App\Responses\NotFoundOrNoRightsResponse;
use App\Responses\NotFoundResponse;
use App\Responses\RedirectResponse;
use App\Util\PasswordGenerator;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class UserController
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * UserController constructor.
     * @param Security $security
     * @param UserRepository $userRepository
     */
    public function __construct(Security $security, UserRepository $userRepository)
    {
        $this->security = $security;
        $this->userRepository = $userRepository;
    }

    /**
     * @Route("/api/users", methods={"GET"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @return Response
     */
    public function getUsers(Request $request, SerializerInterface $serializer)
    {
        $currentUser = $this->security->getUser();
        if (in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            $usersColelction = $this->userRepository->findAll();
        } else {
            $usersColelction = [$currentUser];
        }
        $users = [];
        if (count($usersColelction) > 0) {
            foreach ($usersColelction as $user) {
                $users[] = $user;
            }
        }
        return new JsonResponse($this->serializeUsers($serializer, $users), Response::HTTP_OK, false);
    }

    /**
     * @Route("/api/users", methods={"POST"})
     * @param Request $request
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @param PasswordGenerator $passwordGenerator
     * @return Response
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addUser(Request $request, UserPasswordEncoderInterface $userPasswordEncoder, PasswordGenerator $passwordGenerator)
    {
        if (!in_array('ROLE_ADMIN', $this->security->getUser()->getRoles())) {
            return new NoRightsResponse('add users');
        }
        $body = json_decode($request->getContent(), true);
        if (isset($body['username'])) {
            $username = $body['username'];
            if ($this->userRepository->findOneBy(['username' => $body['username']]) !== null) {
                return new ErrorResponse('Username already taken!', Response::HTTP_BAD_REQUEST);
            }
        } else {
            $taken = true;
            while ($taken) {
                $username = 'User_' . $passwordGenerator->generate(5);
                $taken = ($this->userRepository->findOneBy(['username' => $username]) !== null);
            }
        }
        $user = new User($username);
        if (isset($body['roles'])) {
            foreach ($body['roles'] as $role) {
                $user->addRole($role);
            }
        }
        $password = isset($body['password'])
            ? $body['password']
            : $passwordGenerator->generate();
        $user->setPassword($userPasswordEncoder->encodePassword($user, $password));
        $this->userRepository->save($user);
        return new RedirectResponse('/user/' . $user->getId());
    }

    /**
     * @Route("/api/user/{id}", methods={"GET"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param string $id
     * @return Response
     */
    public function getUser(Request $request, SerializerInterface $serializer, string $id)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            $user = $this->userRepository->findOneBy(['id' => $id]);
        } else {
            $user = $currentUser->getId() === $id ? $currentUser : null;
        }
        if ($user) {
            $json = $this->serializeUsers($serializer, $user);
            return new Response($json);
        }
        return new NotFoundOrNoRightsResponse('user');
    }

    /**
     * @Route("/api/user/{id}", methods={"PATCH"})
     * @param Request $request
     * @param string $id
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @return NoRightsResponse|NotFoundResponse|RedirectResponse|ErrorResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changeUser(Request $request, string $id, UserPasswordEncoderInterface $userPasswordEncoder)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->getId() !== $id && !$currentUser->hasRole("ROLE_ADMIN")) {
            return new NoRightsResponse('change this user');
        }
        $user = $this->userRepository->findOneBy(['id' => $id]);
        if ($user === null) {
            return new NotFoundResponse('user');
        }
        if (isset($body['password'])) {
            $user->setPassword($userPasswordEncoder->encodePassword($user, $body['password']));
        }
        $body = json_decode($request->getContent(), true);
        if ($currentUser->getId() !== $id && $currentUser->hasRole("ROLE_ADMIN")) {
            if (isset($body['roles']) && is_array($body['roles'])) {
                $user->setRoles($body['roles']);
            }
        }
        $this->userRepository->save($user);
        return new RedirectResponse('/user/' . $user->getId());
    }

    /**
     * @Route("/api/user/{id}", methods={"DELETE"})
     * @param Request $request
     * @param string $id
     * @return EmptyResponse|NoRightsResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteUser(Request $request, string $id)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!($currentUser->getId() === $id)
            && !in_array('ROLE_ADMIN', $this->security->getUser()->getRoles())) {
            return new NoRightsResponse('delete this user');
        }
        $user = $this->userRepository->findOneBy(['id' => $id]);
        $this->userRepository->delete($user);
        return new EmptyResponse();
    }

    /**
     * @param SerializerInterface $serializer
     * @param User|User[] $users
     * @return string
     */
    private function serializeUsers(SerializerInterface $serializer, $users)
    {
        $callback = function ($attributeValue, $object, $attribute, $format, $context) {
            switch ($attribute) {
                case 'volumes':
                    $collection = $object->getVolumes();
                    $baseLink = '/volume/';
                    break;
                case 'apiTokens':
                    $collection = $object->getApiTokens();
                    $baseLink = '/user/' . $object->getId() . '/apiToken/';
                    break;
                default:
                    throw new Exception("unexpected");
            }
            $links = [];
            foreach ($collection as $item) {
                $links[] = $baseLink . $item->getId();
            }
            return $links;
        };
        $context = [
            AbstractNormalizer::CALLBACKS =>
                [
                    'volumes' => $callback,
                    'apiTokens' => $callback
                ],
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['password', 'salt']
        ];
        return $serializer->serialize($users, 'json', $context);
    }

}