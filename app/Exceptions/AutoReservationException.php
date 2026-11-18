<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class AutoReservationException extends ReservationException
{
    public function __construct(string $message = "Un étudiant ne peut pas réserver sa propre offre (RG03).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
