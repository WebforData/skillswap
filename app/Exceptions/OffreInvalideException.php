<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class OffreInvalideException extends ReservationException
{
    public function __construct(string $message = "L'offre sélectionnée n'est pas disponible pour une réservation (RG04).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
