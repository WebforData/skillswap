<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class ConfirmationSeanceException extends ReservationException
{
    public function __construct(string $message = "La confirmation de la séance ne peut pas être effectuée (RG11, RG12).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
