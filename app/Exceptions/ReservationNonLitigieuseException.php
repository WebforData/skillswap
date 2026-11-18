<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ReservationNonLitigieuseException extends LitigeException
{
    public function __construct(string $message = "La réservation associée au litige n'est pas au statut 'en_litige'.")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
