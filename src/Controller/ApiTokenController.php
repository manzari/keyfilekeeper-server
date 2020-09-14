<?php


namespace App\Controller;


use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Responses\EmptyResponse;
use App\Responses\JsonResponse;
use App\Responses\NoRightsResponse;
use App\Responses\NotFoundResponse;
use App\Responses\RedirectResponse;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class ApiTokenController
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var ApiTokenRepository
     */
    private $apiTokenRepository;

    /**
     * UserController constructor.
     * @param Security $security
     * @param ApiTokenRepository $apiTokenRepository
     */
    public function __construct(Security $security, ApiTokenRepository $apiTokenRepository)
    {
        $this->security = $security;
        $this->apiTokenRepository = $apiTokenRepository;
    }

    /**
     * @Route("/api/user/{username}/tokens", methods={"POST"})
     * @param UserRepository $userRepository
     * @param string $username
     * @return NoRightsResponse|NotFoundResponse|RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createToken(UserRepository $userRepository, string $username)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->getId() !== $username && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('add this token');
        }
        $user = $userRepository->findOneBy(['id' => $username]);
        if ($user === null) {
            return new NotFoundResponse('user');
        }
        $token = new ApiToken($user);
        $token = $this->apiTokenRepository->save($token);
        return new RedirectResponse('/user/' . $username . '/token/' . $token->getId());
    }

    /**
     * @Route("/api/user/{username}/token/{id}", methods={"DELETE"})
     * @param string $username
     * @param int $id
     * @return EmptyResponse|NoRightsResponse|NotFoundResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteToken(string $username, int $id)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->getId() !== $username && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('delete this token');
        }
        $token = $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        if (!$token->getUser()->getId() === $username) {
            return new NoRightsResponse('delete this users token');
        }
        $this->apiTokenRepository->delete($token);
        return new EmptyResponse();
    }

    /**
     * @Route("/api/user/{username}/tokens", methods={"GET"})
     * @param SerializerInterface $serializer
     * @param string $username
     * @return JsonResponse|NoRightsResponse|NotFoundResponse
     */
    public function getUsersTokens(SerializerInterface $serializer,UserRepository $userRepository, string $username)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $user = $userRepository->findOneBy(['username'=>$username]);
        if ($user === null) {
            return new NotFoundResponse('user');
        }
        if ($currentUser->getId() !== $username && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('see this users tokens');
        }
        $tokens = $this->apiTokenRepository->findBy(['user_id' => $user->getId()]);
        return new JsonResponse($this->serializeTokens($serializer, $tokens));
    }

    /**
     * @Route("/api/tokens", methods={"GET"})
     * @param SerializerInterface $serializer
     * @return JsonResponse|NoRightsResponse|NotFoundResponse
     */
    public function getTokens(SerializerInterface $serializer)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('see this tokens');
        }
        $tokens = $this->apiTokenRepository->findAll();
        return new JsonResponse($this->serializeTokens($serializer, $tokens));
    }

    /**
     * @Route("/api/user/{username}/token/{id}/token", methods={"GET"})
     * @param string $username
     * @param string $id
     * @return JsonResponse|NoRightsResponse
     */
    public function getToken(string $username, string $id)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->getId() === $username) {
            return new NoRightsResponse('see this tokens');
        }
        $token = $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        if (!$token->getUser()->getId() === $username) {
            return new NoRightsResponse('see this users token');
        }
        return new JsonResponse(['token' => $token->getToken()], 200, true);
    }

    /**
     * @param SerializerInterface $serializer
     * @param ApiToken[] $tokens
     * @return string
     */
    private function serializeTokens(SerializerInterface $serializer, array $tokens)
    {
        $callback = function ($attributeValue, $object, $attribute, $format, $context) {
            return '/user/' . $object->getUser()->getUsername();
        };
        $context = [
            AbstractNormalizer::CALLBACKS =>
                [
                    'user' => $callback
                ],
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['token']
        ];
        return $serializer->serialize($tokens, 'json', $context);
    }
}