<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CreneauOccupeException extends CreneauException
{
    public function __construct(string $message = "Impossible de modifier ou supprimer un créneau attribué à une réservation confirmée ou en litige (RG07).")
    {
        parent::__construct($message, Response::HTTP_CONFLICT);
    }
}
