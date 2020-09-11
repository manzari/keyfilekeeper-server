<?php


namespace App\Controller;


use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Responses\EmptyResponse;
use App\Responses\JsonResponse;
use App\Responses\NoRightsResponse;
use App\Responses\NotFoundOrNoRightsResponse;
use App\Responses\NotFoundResponse;
use App\Responses\RedirectResponse;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
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
     * @Route("/api/user/{username}/apiTokens", methods={"POST"})
     * @param UserRepository $userRepository
     * @param string $username
     * @return NoRightsResponse|NotFoundResponse|RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createApiToken(UserRepository $userRepository, string $username)
    {
        if (!$this->isAllowed($username)) {
            return new NoRightsResponse('add this apiToken');
        }
        $user = $userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            return new NotFoundResponse('user');
        }
        $token = new ApiToken($user);
        $token = $this->apiTokenRepository->save($token);
        return new RedirectResponse('/user/' . $username . '/apiToken/' . $token->getId());
    }

    /**
     * @Route("/api/user/{username}/apiToken/{id}", methods={"DELETE"})
     * @param string $username
     * @param int $id
     * @return EmptyResponse|NoRightsResponse|NotFoundResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteApiToken(string $username, int $id)
    {
        if (!$this->isAllowed($username)) {
            return new NoRightsResponse('delete this apiToken');
        }
        $token = $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('apiToken');
        }
        if (!$token->getUser()->getUsername() === $username) {
            return new NoRightsResponse('delete this users apiToken');
        }
        $this->apiTokenRepository->delete($token);
        return new EmptyResponse();
    }

    /**
     * @Route("/api/user/{username}/apiTokens", methods={"GET"})
     * @param SerializerInterface $serializer
     * @param string $username
     * @return JsonResponse|NoRightsResponse|NotFoundResponse
     */
    public function getApiTokens(SerializerInterface $serializer, string $username)
    {
        if (!$this->isAllowed($username)) {
            return new NoRightsResponse('read this apiTokens');
        }
        $tokens = $this->apiTokenRepository->findBy(['id' => $username]);
        return new JsonResponse($this->serializeApiTokens($serializer, $tokens));
    }


    private function getTokenIfAllowed($username, $id): ?ApiToken
    {
        $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token !== null &&
            ($token->getUser()->getUsername() === $username
                || in_array('ROLE_ADMIN', $this->security->getUser()->getRoles()))) {
            return $token;
        }
        return null;
    }

    private function isAllowed(string $username): bool
    {
        return ($this->security->getUser()->getUsername() === $username
            || in_array('ROLE_ADMIN', $this->security->getUser()->getRoles()));
    }

    /**
     * @param SerializerInterface $serializer
     * @param $tokens
     * @return string
     */
    private function serializeApiTokens(SerializerInterface $serializer, $tokens)
    {
        $callback = function ($attributeValue, $object, $attribute, $format, $context) {
            return '/user/'.$object->getUser()->getUsername();
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