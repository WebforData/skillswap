<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CreneauIndisponibleException extends ReservationException
{
    public function __construct(string $message = "Ce créneau n'est plus disponible ou est déjà passé (RG04, RG07).")
    {
        parent::__construct($message, Response::HTTP_CONFLICT);
    }
}
