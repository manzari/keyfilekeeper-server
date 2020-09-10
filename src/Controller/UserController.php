<?php


namespace App\Controller;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Entity\Volume;
use App\Repository\UserRepository;
use App\Responses\EmptyResponse;
use App\Responses\ErrorResponse;
use App\Responses\JsonResponse;
use App\Responses\NoRightsResponse;
use App\Responses\NotFoundOrNoRightsResponse;
use App\Responses\NotFoundResponse;
use App\Responses\RedirectResponse;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Json;

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
     * @Route("/users", methods={"GET"})
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
     * @Route("/users", methods={"POST"})
     * @param Request $request
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @return Response
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addUser(Request $request, UserPasswordEncoderInterface $userPasswordEncoder)
    {
        if (!in_array('ROLE_ADMIN', $this->security->getUser()->getRoles())) {
            return new NoRightsResponse('add users');
        }
        $body = json_decode($request->getContent(), true);
        $user = new User($body['username']);
        foreach ($body['roles'] as $role) {
            $user->addRole($role);
        }
        $user->setPassword($userPasswordEncoder->encodePassword($user, $body['password']));
        try {
            $this->userRepository->save($user);
        } catch (UniqueConstraintViolationException $e) {
            return new ErrorResponse('Username already taken!', Response::HTTP_BAD_REQUEST);
        }
        return new RedirectResponse('/user/' . $user->getUsername());
    }

    /**
     * @Route("/user/{username}", methods={"GET"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param string $username
     * @return Response
     */
    public function getUser(Request $request, SerializerInterface $serializer, string $username)
    {
        $currentUser = $this->security->getUser();
        if (in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            $user = $this->userRepository->findOneBy(['username' => $username]);
        } else {
            $user = $currentUser->getUsername() === $username ? $currentUser : null;
        }
        if ($user) {
            $json = $this->serializeUsers($serializer, $user);
            return new Response($json);
        }
        return new NotFoundOrNoRightsResponse('user');
    }

    /**
     * @Route("/user/{username}", methods={"POST"})
     * @param Request $request
     * @param string $username
     * @param UserPasswordEncoderInterface $userPasswordEncoder
     * @return NoRightsResponse|NotFoundResponse|RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changeUser(Request $request, string $username, UserPasswordEncoderInterface $userPasswordEncoder)
    {
        $currentUser = $this->security->getUser();
        if (!($currentUser->getUsername() === $username)
            && !in_array('ROLE_ADMIN', $this->security->getUser()->getRoles())) {
            return new NoRightsResponse('change this user');
        }
        $body = json_decode($request->getContent(), true);
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            return new NotFoundResponse('user');
        }
        if (isset($body['roles']) && is_array($body['roles'])) {
            $user->setRoles($body['roles']);
        }
        if (isset($body['password'])) {
            $user->setPassword($userPasswordEncoder->encodePassword($user, $body['password']));
        }
        $this->userRepository->save($user);
        return new RedirectResponse('/user/' . $username);
    }

    /**
     * @Route("/user/{username}", methods={"DELETE"})
     * @param Request $request
     * @param string $username
     * @return EmptyResponse|NoRightsResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteUser(Request $request, string $username)
    {
        if (!($this->security->getUser()->getUsername() === $username)
            && !in_array('ROLE_ADMIN', $this->security->getUser()->getRoles())) {
            return new NoRightsResponse('delete this user');
        }
        $user = $this->userRepository->findOneBy(['username' => $username]);
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
                    $baselink = '/volume/';
                    break;
                case 'apiTokens':
                    $collection = $object->getApiTokens();
                    $baselink = '/user/' . $object->getUsername() . '/apiToken/';
                    break;
                default:
                    throw new Exception("unexpected");
            }
            $links = [];
            foreach ($collection as $item) {
                $links[] = $baselink . $item->getId();
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