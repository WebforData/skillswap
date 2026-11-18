<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class DureeCreneauInvalideException extends CreneauException
{
    public function __construct(string $message = "La durée du créneau ne correspond pas à la durée de l'offre (RG02).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
