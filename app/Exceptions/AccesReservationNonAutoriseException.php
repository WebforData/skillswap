<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class AccesReservationNonAutoriseException extends ReservationException
{
    public function __construct(string $message = "Action non autorisée sur cette réservation.")
    {
        parent::__construct($message, Response::HTTP_FORBIDDEN);
    }
}
