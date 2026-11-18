<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CreneauInvalideException extends CreneauException
{
    public function __construct(string $message = "Les horaires du créneau sont invalides ou situés dans le passé (RG04).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
