<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class OffreNonAutoriseeException extends OffreException
{
    public function __construct(string $message = "Vous n'êtes pas autorisé à accéder ou modifier cette offre (RG17).")
    {
        parent::__construct($message, Response::HTTP_FORBIDDEN);
    }
}
