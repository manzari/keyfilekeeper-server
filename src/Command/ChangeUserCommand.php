<?php
// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Util\PasswordGenerator;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class ChangeUserCommand extends Command
{
    protected static $defaultName = 'app:changeuser';

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var UserPasswordEncoderInterface
     */
    private $userPasswordEncoder;

    protected function configure()
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Username')
            ->addArgument('password', InputArgument::OPTIONAL, 'User password')
            ->addArgument('admin', InputArgument::OPTIONAL, 'is the user an admin');
    }

    public function __construct(
        UserRepository $userRepository,
        UserPasswordEncoderInterface $userPasswordEncoder,
        string $name = null
    )
    {
        parent::__construct($name);
        $this->userRepository = $userRepository;
        $this->userPasswordEncoder = $userPasswordEncoder;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $username = $input->getArgument('username');
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            $output->writeln('User not existing, will be created');
            $user = new User($username);
        }
        if ($input->hasArgument('password')) {
            $output->writeln('Changing password');
            $user->setPassword($this->userPasswordEncoder->encodePassword($user, $input->getArgument('password')));
        }
        if ($input->getArgument('admin') === 'yes') {
            $output->writeln('Granting admin role');
            $user->addRole('ROLE_ADMIN');
        }
        if ($input->getArgument('admin') === 'no') {
            $output->writeln('Revoking admin role');
            $user->removeRole('ROLE_ADMIN');
        }
        try {
            $this->userRepository->create($user);
        } catch (OptimisticLockException $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        } catch (ORMException $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }
        $output->writeln('Successfully saved user');
        return Command::SUCCESS;
    }
}