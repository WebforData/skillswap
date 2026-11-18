<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class SoldeInsuffisantPourReservationException extends ReservationException
{
    public function __construct(string $message = "Votre solde de crédits disponible est insuffisant pour formuler cette demande (RG05).")
    {
        parent::__construct($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
