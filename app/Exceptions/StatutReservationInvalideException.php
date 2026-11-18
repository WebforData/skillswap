<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class StatutReservationInvalideException extends ReservationException
{
    public function __construct(string $message = "L'opération demandée est incompatible avec le statut actuel de la réservation (RG09).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
