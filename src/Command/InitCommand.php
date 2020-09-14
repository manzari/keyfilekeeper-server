<?php
// src/Command/CreateUserCommand.php
namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class InitCommand extends Command
{
    protected static $defaultName = 'app:init';

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
        // ...
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
        $admin = $this->userRepository->findOneBy(['username' => 'admin']);
        if ($admin !== null) {
            $output->writeln('Admin user existing, skipping');
            return Command::SUCCESS;
        }
        $password = getenv('ADMIN_INIT_PASSWORD');
        if ($password === false) {
            $output->writeln('Environment Variable ADMIN_INIT_PASSWORD was not provided!');
            return Command::FAILURE;
        }
        $admin = new User('admin');
        $admin->setPassword($this->userPasswordEncoder->encodePassword($admin, $password));
        $admin->addRole('ROLE_ADMIN');
        $this->userRepository->create($admin);
        return Command::SUCCESS;
    }
}