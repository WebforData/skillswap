<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ConflitHoraireException extends ReservationException
{
    public function __construct(string $message = "Conflit de planning détecté : un étudiant ne peut pas avoir deux séances acceptées qui se chevauchent (RG08).")
    {
        parent::__construct($message, Response::HTTP_CONFLICT);
    }
}
