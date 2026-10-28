<?php

namespace App\Command;

use App\Service\ReservationExpiryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:reservations:expire',
    description: 'Expire reservations that have passed their expiration time.'
)]
class ReservationsExpireCommand
{
    public function __construct(
        private readonly ReservationExpiryService $reservationExpiryService,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $count = $this->reservationExpiryService->expireReservations();

        $io->success(sprintf(
            'Expired reservations: %d',
            $count
        ));

        return Command::SUCCESS;
    }
}
