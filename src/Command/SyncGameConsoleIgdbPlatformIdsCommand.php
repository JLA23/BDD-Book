<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\GameConsoleRepository;
use App\Service\IgdbPlatformDefaults;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-game-console-igdb-ids',
    description: 'Renseigne game_console.igdb_platform_id à partir des IDs IGDB connus (filtre recherche API jeux)',
)]
class SyncGameConsoleIgdbPlatformIdsCommand extends Command
{
    public function __construct(
        private GameConsoleRepository $consoleRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Écrase les IDs déjà renseignés si le code a une valeur par défaut');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $updated = 0;
        $skipped = 0;
        $unknown = [];

        foreach ($this->consoleRepo->findAll() as $console) {
            $code = $console->getCode();
            $defaultId = IgdbPlatformDefaults::forConsoleCode($code);

            if ($defaultId === null) {
                if ($console->getIgdbPlatformId() === null) {
                    $unknown[] = $code;
                }
                continue;
            }

            if ($console->getIgdbPlatformId() !== null && !$force) {
                if ($console->getIgdbPlatformId() !== $defaultId) {
                    $io->warning(sprintf(
                        '%s : igdb_platform_id=%d en base, attendu IGDB=%d (utilisez --force pour corriger)',
                        $code,
                        $console->getIgdbPlatformId(),
                        $defaultId
                    ));
                }
                ++$skipped;
                continue;
            }

            $console->setIgdbPlatformId($defaultId);
            ++$updated;
            $io->text(sprintf('%s → igdb_platform_id %d', $code, $defaultId));
        }

        $this->em->flush();

        $io->success(sprintf('%d console(s) mise(s) à jour, %d inchangée(s).', $updated, $skipped));
        if ($unknown !== []) {
            $io->note('Sans correspondance IGDB connue : ' . implode(', ', $unknown));
        }

        return Command::SUCCESS;
    }
}
