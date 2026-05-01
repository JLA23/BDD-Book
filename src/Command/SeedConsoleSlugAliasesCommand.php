<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ConsoleSlugAliasSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-console-slug-aliases',
    description: 'Insère dans game_console_alias les libellés/slugs connus (IGDB, imports) — idempotent'
)]
class SeedConsoleSlugAliasesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConsoleSlugAliasSeeder $slugAliasSeeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->section('Alias consoles (données initiales → base)');
        $n = $this->slugAliasSeeder->seedMissingAliases($io);
        $this->em->flush();
        $io->success($n . ' alias créé(s) (les doublons sont ignorés).');

        return Command::SUCCESS;
    }
}
