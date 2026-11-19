<?php

namespace App\Command;

use App\Service\ReservationExpiryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:expire-reservations', description: 'Marks expired inventory reservations as EXPIRED.')]
final class ExpireReservationsCommand extends Command
{
    public function __construct(private readonly ReservationExpiryService $service)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->success(sprintf('%d reservation(s) expired.', $this->service->expireReservations()));

        return Command::SUCCESS;
    }
}


